<?php

namespace Domains\Character\Actions;

use App\Services\CharacterAvatarService;
use Domains\Character\Models\Character;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteCharacterAction
{
    public function __construct(
        private CharacterAvatarService $characterAvatarService
    ) {}

    public function __invoke(Character $character): void
    {
        if ($character->guildMember()->exists()) {
            throw new HttpResponseException(
                response()->json(['message' => 'Нельзя удалить персонажа: он состоит в гильдии. Сначала выйдите из гильдии.'], 422)
            );
        }

        if ($character->avatar) {
            $this->characterAvatarService->deleteAvatar($character->avatar);
        }
        $character->delete();
    }
}
