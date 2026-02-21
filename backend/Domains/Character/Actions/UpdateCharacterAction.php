<?php

namespace Domains\Character\Actions;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Services\CharacterAvatarService;
use Domains\Character\Models\Character;
use Illuminate\Http\UploadedFile;

class UpdateCharacterAction
{
    public function __construct(
        private CharacterRepositoryInterface $characterRepository,
        private CharacterAvatarService $characterAvatarService
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(Character $character, array $data, ?UploadedFile $avatar = null, bool $removeAvatar = false): Character
    {
        if ($removeAvatar && $character->avatar) {
            $this->characterAvatarService->deleteAvatar($character->avatar);
            $data['avatar'] = null;
        }
        $gameClassIds = $data['game_class_ids'] ?? [];
        $tagIds = $data['tag_ids'] ?? null;
        $isMain = isset($data['is_main']) ? (bool) $data['is_main'] : null;
        unset($data['avatar'], $data['remove_avatar'], $data['game_class_ids'], $data['tag_ids'], $data['is_main']);
        if ($isMain === true) {
            Character::query()
                ->where('user_id', $character->user_id)
                ->where('game_id', $character->game_id)
                ->where('id', '!=', $character->id)
                ->update(['is_main' => false]);
            $data['is_main'] = true;
        } elseif ($isMain === false) {
            $data['is_main'] = false;
        }
        $character = $this->characterRepository->update($character, $data);
        $character->gameClasses()->sync(is_array($gameClassIds) ? $gameClassIds : []);
        if ($tagIds !== null) {
            $character->tags()->sync(is_array($tagIds) ? array_map('intval', $tagIds) : []);
        }
        if ($avatar) {
            if ($character->avatar) {
                $this->characterAvatarService->deleteAvatar($character->avatar);
            }
            $avatarDir = $this->characterAvatarService->storeAvatar($avatar, $character->id);
            $character = $this->characterRepository->update($character, ['avatar' => $avatarDir]);
        }
        $character->load(['game', 'localization', 'server', 'gameClasses', 'tags']);
        return $character;
    }
}
