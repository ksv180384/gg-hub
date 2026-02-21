<?php

namespace Domains\Character\Actions;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Models\User;
use App\Services\CharacterAvatarService;
use Domains\Character\Models\Character;
use Illuminate\Http\UploadedFile;

class CreateCharacterAction
{
    public function __construct(
        private CharacterRepositoryInterface $characterRepository,
        private CharacterAvatarService $characterAvatarService
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(User $user, array $data, ?UploadedFile $avatar = null): Character
    {
        $data['user_id'] = $user->id;
        $gameId = (int) ($data['game_id'] ?? 0);
        $gameClassIds = $data['game_class_ids'] ?? [];
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['avatar'], $data['game_class_ids'], $data['tag_ids']);
        $character = $this->characterRepository->create($data);
        $character->gameClasses()->sync(is_array($gameClassIds) ? $gameClassIds : []);
        $character->tags()->sync(is_array($tagIds) ? array_map('intval', $tagIds) : []);
        if ($avatar) {
            $avatarDir = $this->characterAvatarService->storeAvatar($avatar, $character->id);
            $this->characterRepository->update($character, ['avatar' => $avatarDir]);
            $character->avatar = $avatarDir;
        }
        $countInGame = Character::query()
            ->where('user_id', $user->id)
            ->where('game_id', $gameId)
            ->count();
        if ($countInGame === 1) {
            $this->characterRepository->update($character, ['is_main' => true]);
            $character->is_main = true;
        }
        $character->load(['game', 'localization', 'server', 'gameClasses', 'tags']);
        return $character;
    }
}
