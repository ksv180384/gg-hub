<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConstantParty\StoreConstantPartyChatMessageRequest;
use App\Http\Requests\ConstantParty\UpdateConstantPartyChatReceiptsRequest;
use App\Http\Resources\ConstantParty\ConstantPartyChatMessageResource;
use App\Services\ConstantPartyChatSocketBroadcaster;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyChatMessage;
use Domains\ConstantParty\Models\ConstantPartyChatMessageReceipt;
use Domains\ConstantParty\Models\ConstantPartyChatSocketToken;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConstantPartyChatController extends Controller
{
    public function __construct(
        private ConstantPartyChatSocketBroadcaster $socketBroadcaster,
    ) {}

    public function index(Request $request, ConstantParty $constantParty): AnonymousResourceCollection
    {
        $this->ensureMember($constantParty, $request->user()->id);

        $messages = ConstantPartyChatMessage::query()
            ->withReceiptSummary()
            ->where('constant_party_id', $constantParty->id)
            ->with('character.gameClasses')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return ConstantPartyChatMessageResource::collection($messages);
    }

    public function socketToken(Request $request, ConstantParty $constantParty): JsonResponse
    {
        $this->ensureMember($constantParty, $request->user()->id);
        $data = $request->validate([
            'character_id' => ['required', 'integer', 'exists:characters,id'],
        ]);
        $characterId = (int) $data['character_id'];
        $this->ensureUserOwnsPartyCharacter($constantParty, $request->user()->id, $characterId);

        ConstantPartyChatSocketToken::query()
            ->where('expires_at', '<=', now())
            ->delete();

        $plainToken = Str::random(80);
        $expiresAt = now()->addMinutes(10);
        ConstantPartyChatSocketToken::query()->create([
            'token_hash' => hash('sha256', $plainToken),
            'constant_party_id' => $constantParty->id,
            'character_id' => $characterId,
            'user_id' => $request->user()->id,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'data' => [
                'token' => $plainToken,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    public function store(
        StoreConstantPartyChatMessageRequest $request,
        ConstantParty $constantParty
    ): ConstantPartyChatMessageResource {
        $this->ensureMember($constantParty, $request->user()->id);
        $data = $request->validated();
        $characterId = (int) $data['character_id'];
        $this->ensureUserOwnsPartyCharacter($constantParty, $request->user()->id, $characterId);

        $messageId = DB::transaction(function () use ($constantParty, $characterId, $data): int {
            $message = ConstantPartyChatMessage::query()->create([
                'constant_party_id' => $constantParty->id,
                'character_id' => $characterId,
                'body' => trim((string) $data['body']),
            ]);

            $now = now();
            $recipientCharacterIds = ConstantPartyMember::query()
                ->where('constant_party_id', $constantParty->id)
                ->where('character_id', '!=', $characterId)
                ->pluck('character_id');

            if ($recipientCharacterIds->isNotEmpty()) {
                ConstantPartyChatMessageReceipt::query()->insert(
                    $recipientCharacterIds
                        ->map(fn ($recipientCharacterId): array => [
                            'message_id' => $message->id,
                            'character_id' => $recipientCharacterId,
                            'delivered_at' => null,
                            'read_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            }

            return (int) $message->id;
        });

        $message = $this->messageQuery()->findOrFail($messageId);
        $this->socketBroadcaster->broadcastCreated($message);

        return new ConstantPartyChatMessageResource($message);
    }

    public function markDelivered(
        UpdateConstantPartyChatReceiptsRequest $request,
        ConstantParty $constantParty
    ): AnonymousResourceCollection {
        return $this->updateReceipts($request, $constantParty, false);
    }

    public function markRead(
        UpdateConstantPartyChatReceiptsRequest $request,
        ConstantParty $constantParty
    ): AnonymousResourceCollection {
        return $this->updateReceipts($request, $constantParty, true);
    }

    public function destroy(
        Request $request,
        ConstantParty $constantParty,
        ConstantPartyChatMessage $message
    ): Response {
        $member = $this->ensureMember($constantParty, $request->user()->id);
        if ((int) $message->constant_party_id !== (int) $constantParty->id) {
            abort(404);
        }
        $ownsMessage = $message->character()->where('user_id', $request->user()->id)->exists();
        if (! $ownsMessage && $member->role !== ConstantPartyMember::ROLE_LEADER) {
            abort(403);
        }

        $messageId = (int) $message->id;
        $message->delete();
        $this->socketBroadcaster->broadcastDeleted((int) $constantParty->id, $messageId);

        return response()->noContent();
    }

    private function updateReceipts(
        UpdateConstantPartyChatReceiptsRequest $request,
        ConstantParty $constantParty,
        bool $markRead
    ): AnonymousResourceCollection {
        $this->ensureMember($constantParty, $request->user()->id);
        $data = $request->validated();
        $characterId = (int) $data['character_id'];
        $this->ensureUserOwnsPartyCharacter($constantParty, $request->user()->id, $characterId);

        $receipts = ConstantPartyChatMessageReceipt::query()
            ->where('character_id', $characterId)
            ->whereIn('message_id', $data['message_ids'])
            ->whereHas('message', fn ($query) => $query->where('constant_party_id', $constantParty->id))
            ->get();

        $now = now();
        DB::transaction(function () use ($markRead, $now, $receipts): void {
            foreach ($receipts as $receipt) {
                $receipt->delivered_at ??= $now;
                if ($markRead) {
                    $receipt->read_at ??= $now;
                }
                if ($receipt->isDirty()) {
                    $receipt->save();
                }
            }
        });

        $messageIds = $receipts->pluck('message_id')->unique()->values();
        $messages = $messageIds->isEmpty()
            ? collect()
            : $this->messageQuery()->whereIn('id', $messageIds)->get();

        $this->socketBroadcaster->broadcastReceiptsChanged(
            (int) $constantParty->id,
            $this->receiptSummaries($messages),
        );

        return ConstantPartyChatMessageResource::collection($messages);
    }

    private function messageQuery()
    {
        return ConstantPartyChatMessage::query()
            ->withReceiptSummary()
            ->with('character.gameClasses');
    }

    /**
     * @param  Collection<int, ConstantPartyChatMessage>  $messages
     * @return array<int, array<string, int|string>>
     */
    private function receiptSummaries(Collection $messages): array
    {
        return $messages
            ->map(fn (ConstantPartyChatMessage $message): array => [
                'id' => (int) $message->id,
                'recipient_count' => (int) $message->recipient_count,
                'delivered_count' => (int) $message->delivered_count,
                'read_count' => (int) $message->read_count,
                'delivery_status' => $message->deliveryStatus(),
            ])
            ->values()
            ->all();
    }

    private function ensureMember(ConstantParty $party, int $userId): ConstantPartyMember
    {
        $member = ConstantPartyMember::query()
            ->where('constant_party_id', $party->id)
            ->whereHas('character', fn ($query) => $query->where('user_id', $userId))
            ->first();

        if (! $member) {
            abort(403);
        }

        return $member;
    }

    private function ensureUserOwnsPartyCharacter(ConstantParty $party, int $userId, int $characterId): void
    {
        $exists = ConstantPartyMember::query()
            ->where('constant_party_id', $party->id)
            ->where('character_id', $characterId)
            ->whereHas('character', fn ($query) => $query->where('user_id', $userId))
            ->exists();

        if (! $exists) {
            abort(403);
        }
    }
}
