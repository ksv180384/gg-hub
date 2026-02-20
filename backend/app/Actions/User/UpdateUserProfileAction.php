<?php

namespace App\Actions\User;

use App\Models\User;
use App\Services\UserAvatarService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateUserProfileAction
{
    public function __construct(
        private UserAvatarService $userAvatarService
    ) {
    }

    /**
     * Обновляет профиль пользователя: имя, часовой пояс, при необходимости — аватар.
     *
     * @param  array{name: string, timezone?: string|null}  $data
     */
    public function execute(User $user, array $data, ?UploadedFile $avatarFile = null): User
    {
        $user->name = $data['name'];

        if (array_key_exists('timezone', $data)) {
            $user->timezone = $data['timezone'] ?? 'UTC';
        }

        if ($avatarFile !== null) {
            if ($user->avatar) {
                if (str_starts_with($user->avatar, 'users/')) {
                    $this->userAvatarService->deleteAvatar($user->avatar);
                } else {
                    Storage::disk('public')->delete($user->avatar);
                }
            }
            $user->avatar = $this->userAvatarService->storeAvatar($avatarFile, $user->id);
        }

        $user->save();
        $user->load('roles', 'directPermissions');

        return $user;
    }
}
