<?php

namespace Domains\User\Actions;

use App\Models\User;
use Illuminate\Support\Str;

class ResolveRegistrationUserNameFromEmailAction
{
    /**
     * Имя из локальной части email; при занятости добавляет суффикс 2, 3, …
     */
    public function __invoke(string $email): string
    {
        $localPart = Str::before(Str::lower(trim($email)), '@');
        $base = trim($localPart) !== '' ? trim($localPart) : 'user';

        return $this->resolveUniqueName($base);
    }

    private function resolveUniqueName(string $base): string
    {
        $base = Str::limit($base, 255, '');

        if (! $this->nameExists($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= 9999; $suffix++) {
            $suffixStr = (string) $suffix;
            $maxBaseLength = 255 - strlen($suffixStr);
            $candidate = Str::limit($base, $maxBaseLength, '').$suffixStr;

            if (! $this->nameExists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Не удалось подобрать уникальное имя пользователя.');
    }

    private function nameExists(string $name): bool
    {
        return User::query()->where('name', $name)->exists();
    }
}
