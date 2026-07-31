<?php

namespace App\Actions\User;

use App\Services\UserAvatarService;
use Domains\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateUserProfileAction
{
    public function __construct(
        private UserAvatarService $userAvatarService
    ) {}

    /**
     * Обновляет профиль пользователя: имя, часовой пояс, при необходимости — аватар.
     *
     * @param  array{name?: string, timezone?: string|null, theme_preference?: string}  $data
     */
    public function __invoke(User $user, array $data, ?UploadedFile $avatarFile = null): User
    {
        if (array_key_exists('name', $data)) {
            $user->name = trim((string) ($data['name'] ?? ''));
        }

        if (array_key_exists('timezone', $data)) {
            $user->timezone = $data['timezone'] ?? 'UTC';
        }

        if (array_key_exists('theme_preference', $data)) {
            $user->theme_preference = $data['theme_preference'];
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
