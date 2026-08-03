<?php

namespace Domains\Character\Actions;

use App\Repositories\Eloquent\EloquentCharacterRepository;
use App\Services\CharacterAvatarService;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Actions\NotifyConstantPartyMembershipChangedAction;
use Domains\ConstantParty\Actions\RecordConstantPartyMembershipLogAction;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\ConstantParty\Models\ConstantPartyStorageLog;
use Illuminate\Http\UploadedFile;

class UpdateCharacterAction
{
    public function __construct(
        private EloquentCharacterRepository $characterRepository,
        private CharacterAvatarService $characterAvatarService,
        private RecordConstantPartyMembershipLogAction $recordMembershipLog,
        private NotifyConstantPartyMembershipChangedAction $notifyMembershipChanged,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Character $character, array $data, ?UploadedFile $avatar = null, bool $removeAvatar = false): Character
    {
        $oldServerId = (int) $character->server_id;
        $newServerId = isset($data['server_id']) ? (int) $data['server_id'] : $oldServerId;
        if ($newServerId !== $oldServerId) {
            $leaderPartyName = ConstantPartyMember::query()
                ->where('character_id', $character->id)
                ->where('role', ConstantPartyMember::ROLE_LEADER)
                ->with('constantParty:id,name')
                ->first()
                ?->constantParty
                ?->name;

            if ($leaderPartyName !== null) {
                abort(422, "Нельзя сменить сервер: персонаж является лидером конст пати «{$leaderPartyName}».");
            }
        }

        if ($removeAvatar && $character->avatar) {
            $this->characterAvatarService->deleteAvatar($character->avatar);
            $data['avatar'] = null;
        }
        $gameClassIds = $data['game_class_ids'] ?? [];
        $syncTags = array_key_exists('tag_ids', $data);
        $tagIds = $syncTags ? (is_array($data['tag_ids'] ?? null) ? $data['tag_ids'] : []) : null;
        $isMain = isset($data['is_main']) ? (bool) $data['is_main'] : null;
        unset($data['avatar'], $data['remove_avatar'], $data['game_class_ids'], $data['tag_ids'], $data['is_main']);
        if (array_key_exists('use_profile_avatar', $data)) {
            $data['use_profile_avatar'] = (bool) $data['use_profile_avatar'];
        }
        if ($isMain === true) {
            Character::query()
                ->where('user_id', $character->user_id)
                ->where('game_id', $character->game_id)
                ->where('id', '!=', $character->id)
                ->update(['is_main' => false]);
            $data['is_main'] = true;
        } elseif ($isMain === false && ! $character->is_main) {
            $data['is_main'] = false;
        }
        $character = $this->characterRepository->update($character, $data);
        $character->gameClasses()->sync(is_array($gameClassIds) ? $gameClassIds : []);
        if ($syncTags) {
            $character->tags()->sync(array_map('intval', $tagIds ?? []));
        }
        if ($avatar) {
            if ($character->avatar) {
                $this->characterAvatarService->deleteAvatar($character->avatar);
            }
            $avatarDir = $this->characterAvatarService->storeAvatar($avatar, $character->id);
            $character = $this->characterRepository->update($character, ['avatar' => $avatarDir]);
        }
        if ($newServerId !== $oldServerId) {
            $this->removeFromConstantPartiesAfterServerChange($character);
        }
        $character->load(['game', 'localization', 'server', 'gameClasses', 'tags.createdByUser']);

        return $character;
    }

    private function removeFromConstantPartiesAfterServerChange(Character $character): void
    {
        $members = ConstantPartyMember::query()
            ->where('character_id', $character->id)
            ->with('constantParty:id,name,leader_character_id')
            ->get();

        foreach ($members as $member) {
            $party = $member->constantParty;
            $recipientUserIds = $this->notifyMembershipChanged->recipientUserIds($party);
            ($this->recordMembershipLog)(
                $party,
                ConstantPartyStorageLog::ACTION_MEMBER_REMOVED,
                $character->id,
                $character->id,
                ['source' => 'server_changed'],
            );
            ($this->notifyMembershipChanged)(
                $party,
                ConstantPartyStorageLog::ACTION_MEMBER_REMOVED,
                $character,
                $character,
                $recipientUserIds,
                'server_changed',
            );
            $member->delete();
        }
    }
}
