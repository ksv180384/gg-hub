<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConstantParty\InviteConstantPartyCharacterRequest;
use App\Http\Requests\ConstantParty\StoreConstantPartyRequest;
use App\Http\Requests\ConstantParty\UpdateConstantPartyMemberRequest;
use App\Http\Resources\Character\CharacterResource;
use App\Http\Resources\ConstantParty\ConstantPartyInvitationResource;
use App\Http\Resources\ConstantParty\ConstantPartyMemberResource;
use App\Http\Resources\ConstantParty\ConstantPartyResource;
use App\Services\SubdomainContext;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyInvitation;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\Notification\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ConstantPartyController extends Controller
{
    public function __construct(
        private SubdomainContext $subdomainContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $gameId = $this->resolveGameId($request);

        $parties = ConstantParty::query()
            ->where('game_id', $gameId)
            ->whereHas('members.character', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'leader.gameClasses',
                'leader.server',
                'leader.localization',
                'game',
                'localization',
                'server',
                'members.character.gameClasses',
                'members.character.server',
            ])
            ->withCount('members')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (ConstantParty $party) use ($user): ConstantParty {
                $member = $party->members->first(
                    fn (ConstantPartyMember $member) => (int) $member->character?->user_id === (int) $user->id
                );
                $party->my_member = $member ? [
                    'id' => $member->id,
                    'character_id' => $member->character_id,
                    'role' => $member->role,
                    'can_manage_storage' => (bool) $member->can_manage_storage,
                ] : null;

                return $party;
            });

        $invitations = ConstantPartyInvitation::query()
            ->where('status', ConstantPartyInvitation::STATUS_PENDING)
            ->whereHas('constantParty', fn ($query) => $query->where('game_id', $gameId))
            ->whereHas('invitedCharacter', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'constantParty.leader',
                'constantParty.server',
                'invitedCharacter',
                'invitedByCharacter',
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ConstantPartyResource::collection($parties),
            'invitations' => ConstantPartyInvitationResource::collection($invitations),
        ]);
    }

    public function store(StoreConstantPartyRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $gameId = $this->resolveGameId($request);

        $party = DB::transaction(function () use ($user, $data, $gameId): ConstantParty {
            /** @var Character $leader */
            $leader = Character::query()
                ->whereKey($data['leader_character_id'])
                ->where('game_id', $gameId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->abortIfCharacterAlreadyInParty((int) $leader->id);

            $party = ConstantParty::query()->create([
                'leader_character_id' => $leader->id,
                'game_id' => $leader->game_id,
                'localization_id' => $leader->localization_id,
                'server_id' => $leader->server_id,
                'created_by_user_id' => $user->id,
                'name' => trim((string) $data['name']),
            ]);

            ConstantPartyMember::query()->create([
                'constant_party_id' => $party->id,
                'character_id' => $leader->id,
                'role' => ConstantPartyMember::ROLE_LEADER,
                'can_manage_storage' => true,
                'joined_at' => now(),
            ]);

            return $party;
        });

        $party->load(['leader.gameClasses', 'server', 'localization', 'game', 'members.character']);

        return (new ConstantPartyResource($party))->response()->setStatusCode(201);
    }

    public function show(Request $request, ConstantParty $constantParty): ConstantPartyResource
    {
        $gameId = $this->resolveGameId($request);

        if ((int) $constantParty->game_id !== $gameId) {
            abort(404);
        }

        $currentMember = $this->ensureMember($constantParty, $request->user()->id);

        $constantParty->load([
            'leader.gameClasses',
            'leader.server',
            'leader.localization',
            'game',
            'localization',
            'server',
            'members.character.gameClasses',
            'members.character.server',
            'members.character.user',
        ]);
        $constantParty->my_member = [
            'id' => $currentMember->id,
            'character_id' => $currentMember->character_id,
            'role' => $currentMember->role,
            'can_manage_storage' => (bool) $currentMember->can_manage_storage,
        ];

        return new ConstantPartyResource($constantParty);
    }

    public function updateMember(
        UpdateConstantPartyMemberRequest $request,
        ConstantParty $constantParty,
        ConstantPartyMember $member
    ): ConstantPartyMemberResource {
        $this->ensureLeader($constantParty, $request->user()->id);
        $this->ensureMemberBelongsToParty($constantParty, $member);

        if ($member->role === ConstantPartyMember::ROLE_LEADER) {
            abort(422, 'Нельзя снять права на хранилище у лидера КП.');
        }

        $member->can_manage_storage = (bool) $request->validated('can_manage_storage');
        $member->save();
        $member->load('character');

        return new ConstantPartyMemberResource($member);
    }

    public function destroyMember(Request $request, ConstantParty $constantParty, ConstantPartyMember $member): Response
    {
        $this->ensureLeader($constantParty, $request->user()->id);
        $this->ensureMemberBelongsToParty($constantParty, $member);

        if ($member->role === ConstantPartyMember::ROLE_LEADER) {
            abort(422, 'Нельзя исключить лидера КП.');
        }

        $member->delete();

        return response()->noContent();
    }

    public function transferLeadership(
        Request $request,
        ConstantParty $constantParty,
        ConstantPartyMember $member
    ): ConstantPartyResource {
        $party = DB::transaction(function () use ($request, $constantParty, $member): ConstantParty {
            /** @var ConstantParty $lockedParty */
            $lockedParty = ConstantParty::query()
                ->whereKey($constantParty->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentLeader = ConstantPartyMember::query()
                ->where('constant_party_id', $lockedParty->id)
                ->where('role', ConstantPartyMember::ROLE_LEADER)
                ->whereHas('character', fn ($query) => $query->where('user_id', $request->user()->id))
                ->lockForUpdate()
                ->first();

            if (! $currentLeader) {
                abort(403);
            }

            /** @var ConstantPartyMember|null $newLeader */
            $newLeader = ConstantPartyMember::query()
                ->whereKey($member->id)
                ->where('constant_party_id', $lockedParty->id)
                ->with('character')
                ->lockForUpdate()
                ->first();

            if (! $newLeader) {
                abort(404);
            }
            if ($newLeader->role === ConstantPartyMember::ROLE_LEADER) {
                abort(422, 'Выбранный персонаж уже является лидером КП.');
            }

            $currentLeader->update([
                'role' => ConstantPartyMember::ROLE_MEMBER,
                'can_manage_storage' => false,
            ]);
            $newLeader->update([
                'role' => ConstantPartyMember::ROLE_LEADER,
                'can_manage_storage' => true,
            ]);
            $lockedParty->update([
                'leader_character_id' => $newLeader->character_id,
            ]);

            Notification::query()->create([
                'user_id' => $newLeader->character->user_id,
                'message' => "Персонаж {$newLeader->character->name} стал лидером КП «{$lockedParty->name}».",
                'link' => "/constant-parties/{$lockedParty->id}",
            ]);

            return $lockedParty;
        });

        $currentMember = $this->ensureMember($party, $request->user()->id);
        $party->load([
            'leader.gameClasses',
            'leader.server',
            'leader.localization',
            'game',
            'localization',
            'server',
            'members.character.gameClasses',
            'members.character.server',
            'members.character.user',
        ]);
        $party->my_member = [
            'id' => $currentMember->id,
            'character_id' => $currentMember->character_id,
            'role' => $currentMember->role,
            'can_manage_storage' => (bool) $currentMember->can_manage_storage,
        ];

        return new ConstantPartyResource($party);
    }

    public function invitations(Request $request, ConstantParty $constantParty): AnonymousResourceCollection
    {
        $this->ensureMember($constantParty, $request->user()->id);

        $invitations = ConstantPartyInvitation::query()
            ->where('constant_party_id', $constantParty->id)
            ->with(['invitedCharacter', 'invitedByCharacter'])
            ->orderByDesc('created_at')
            ->get();

        return ConstantPartyInvitationResource::collection($invitations);
    }

    public function inviteCandidates(Request $request, ConstantParty $constantParty): AnonymousResourceCollection
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);

        $query = trim((string) $request->query('query', ''));
        if (mb_strlen($query) < 2) {
            return CharacterResource::collection(collect());
        }

        $characters = Character::query()
            ->where('server_id', $constantParty->server_id)
            ->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%')
            ->whereDoesntHave('constantPartyMember')
            ->whereNotIn('id', function ($subquery) use ($constantParty) {
                $subquery
                    ->select('invited_character_id')
                    ->from('constant_party_invitations')
                    ->where('constant_party_id', $constantParty->id)
                    ->where('status', ConstantPartyInvitation::STATUS_PENDING);
            })
            ->with(['server', 'localization', 'gameClasses'])
            ->orderBy('name')
            ->limit(10)
            ->get();

        return CharacterResource::collection($characters);
    }

    public function invite(InviteConstantPartyCharacterRequest $request, ConstantParty $constantParty): JsonResponse
    {
        $actorMember = $this->ensureStorageManager($constantParty, $request->user()->id);
        $data = $request->validated();

        $invitation = DB::transaction(function () use ($constantParty, $data, $actorMember): ConstantPartyInvitation {
            /** @var Character $character */
            $character = Character::query()
                ->whereKey($data['character_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $character->server_id !== (int) $constantParty->server_id) {
                abort(422, 'Персонаж должен находиться на том же сервере, что и лидер КП.');
            }

            $this->abortIfCharacterAlreadyInParty((int) $character->id);

            $pendingExists = ConstantPartyInvitation::query()
                ->where('constant_party_id', $constantParty->id)
                ->where('invited_character_id', $character->id)
                ->where('status', ConstantPartyInvitation::STATUS_PENDING)
                ->exists();
            if ($pendingExists) {
                abort(422, 'Этому персонажу уже отправлено приглашение.');
            }

            $invitedByCharacterId = (int) ($data['invited_by_character_id'] ?? $actorMember->character_id);
            $inviterIsMember = ConstantPartyMember::query()
                ->where('constant_party_id', $constantParty->id)
                ->where('character_id', $invitedByCharacterId)
                ->exists();
            if (! $inviterIsMember) {
                abort(403);
            }

            $invitation = ConstantPartyInvitation::query()->create([
                'constant_party_id' => $constantParty->id,
                'invited_character_id' => $character->id,
                'invited_by_character_id' => $invitedByCharacterId,
                'status' => ConstantPartyInvitation::STATUS_PENDING,
                'message' => isset($data['message']) ? trim((string) $data['message']) : null,
            ]);

            Notification::query()->create([
                'user_id' => $character->user_id,
                'message' => "Вас пригласили в конст пати «{$constantParty->name}».",
                'link' => '/my-constant-parties',
            ]);

            return $invitation;
        });

        $invitation->load(['constantParty.leader', 'invitedCharacter', 'invitedByCharacter']);

        return (new ConstantPartyInvitationResource($invitation))->response()->setStatusCode(201);
    }

    public function acceptInvitation(Request $request, ConstantPartyInvitation $invitation): ConstantPartyInvitationResource
    {
        $user = $request->user();

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->load(['constantParty', 'invitedCharacter', 'invitedByCharacter']);

            if ((int) $invitation->invitedCharacter->user_id !== (int) $user->id) {
                abort(403);
            }
            if ($invitation->status !== ConstantPartyInvitation::STATUS_PENDING) {
                abort(422, 'Приглашение уже обработано.');
            }
            if ((int) $invitation->invitedCharacter->server_id !== (int) $invitation->constantParty->server_id) {
                $invitation->update([
                    'status' => ConstantPartyInvitation::STATUS_EXPIRED,
                    'responded_at' => now(),
                ]);
                abort(422, 'Персонаж больше не находится на сервере этой КП.');
            }

            $this->abortIfCharacterAlreadyInParty((int) $invitation->invited_character_id);

            ConstantPartyMember::query()->create([
                'constant_party_id' => $invitation->constant_party_id,
                'character_id' => $invitation->invited_character_id,
                'role' => ConstantPartyMember::ROLE_MEMBER,
                'can_manage_storage' => false,
                'joined_at' => now(),
            ]);

            $invitation->update([
                'status' => ConstantPartyInvitation::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            Notification::query()->create([
                'user_id' => $invitation->invitedByCharacter->user_id,
                'message' => "Персонаж {$invitation->invitedCharacter->name} принял приглашение в КП «{$invitation->constantParty->name}».",
                'link' => "/constant-parties/{$invitation->constant_party_id}",
            ]);
        });

        $invitation->load(['constantParty.leader', 'invitedCharacter', 'invitedByCharacter']);

        return new ConstantPartyInvitationResource($invitation);
    }

    public function declineInvitation(Request $request, ConstantPartyInvitation $invitation): ConstantPartyInvitationResource
    {
        $this->ensureInvitationOwner($request, $invitation);
        if ($invitation->status !== ConstantPartyInvitation::STATUS_PENDING) {
            abort(422, 'Приглашение уже обработано.');
        }

        $invitation->update([
            'status' => ConstantPartyInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ]);
        $invitation->load(['constantParty.leader', 'invitedCharacter', 'invitedByCharacter']);

        Notification::query()->create([
            'user_id' => $invitation->invitedByCharacter->user_id,
            'message' => "Персонаж {$invitation->invitedCharacter->name} отклонил приглашение в КП «{$invitation->constantParty->name}».",
            'link' => "/constant-parties/{$invitation->constant_party_id}",
        ]);

        return new ConstantPartyInvitationResource($invitation);
    }

    public function revokeInvitation(Request $request, ConstantParty $constantParty, ConstantPartyInvitation $invitation): ConstantPartyInvitationResource
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        if ((int) $invitation->constant_party_id !== (int) $constantParty->id) {
            abort(404);
        }
        if ($invitation->status !== ConstantPartyInvitation::STATUS_PENDING) {
            abort(422, 'Приглашение уже обработано.');
        }

        $invitation->update([
            'status' => ConstantPartyInvitation::STATUS_REVOKED,
            'responded_at' => now(),
        ]);
        $invitation->load(['constantParty.leader', 'invitedCharacter', 'invitedByCharacter']);

        return new ConstantPartyInvitationResource($invitation);
    }

    private function resolveGameId(Request $request): int
    {
        $gameId = (int) $request->validate([
            'game_id' => ['required', 'integer', 'exists:games,id'],
        ])['game_id'];
        $contextGame = $this->subdomainContext->getGameBySubdomain($request);

        if ($contextGame && (int) $contextGame->id !== $gameId) {
            abort(404);
        }

        return $gameId;
    }

    private function abortIfCharacterAlreadyInParty(int $characterId): void
    {
        if (ConstantPartyMember::query()->where('character_id', $characterId)->exists()) {
            abort(422, 'Персонаж уже состоит в конст пати.');
        }
    }

    private function ensureMember(ConstantParty $party, int $userId): ConstantPartyMember
    {
        $member = ConstantPartyMember::query()
            ->where('constant_party_id', $party->id)
            ->whereHas('character', fn ($query) => $query->where('user_id', $userId))
            ->orderByRaw(
                'CASE WHEN role = ? THEN 0 WHEN can_manage_storage = ? THEN 1 ELSE 2 END',
                [ConstantPartyMember::ROLE_LEADER, true]
            )
            ->first();

        if (! $member) {
            abort(403);
        }

        return $member;
    }

    private function ensureLeader(ConstantParty $party, int $userId): ConstantPartyMember
    {
        $member = $this->ensureMember($party, $userId);
        if ($member->role !== ConstantPartyMember::ROLE_LEADER) {
            abort(403);
        }

        return $member;
    }

    private function ensureStorageManager(ConstantParty $party, int $userId): ConstantPartyMember
    {
        $member = $this->ensureMember($party, $userId);
        if ($member->role !== ConstantPartyMember::ROLE_LEADER && ! $member->can_manage_storage) {
            abort(403);
        }

        return $member;
    }

    private function ensureMemberBelongsToParty(ConstantParty $party, ConstantPartyMember $member): void
    {
        if ((int) $member->constant_party_id !== (int) $party->id) {
            abort(404);
        }
    }

    private function ensureInvitationOwner(Request $request, ConstantPartyInvitation $invitation): void
    {
        $invitation->loadMissing('invitedCharacter');
        if ((int) $invitation->invitedCharacter->user_id !== (int) $request->user()->id) {
            abort(403);
        }
    }
}
