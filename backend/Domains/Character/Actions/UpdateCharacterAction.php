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
        unset($data['avatar'], $data['remove_avatar'], $data['game_class_ids']);
        $character = $this->characterRepository->update($character, $data);
        $character->gameClasses()->sync(is_array($gameClassIds) ? $gameClassIds : []);
        if ($avatar) {
            if ($character->avatar) {
                $this->characterAvatarService->deleteAvatar($character->avatar);
            }
            $avatarDir = $this->characterAvatarService->storeAvatar($avatar, $character->id);
            $character = $this->characterRepository->update($character, ['avatar' => $avatarDir]);
        }
        $character->load(['game', 'localization', 'server', 'gameClasses']);
        return $character;
    }
}
