<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Domains\Access\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public const DEFAULT_ROLE_SLUG = 'polzovatel';

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ], [
            'email.required' => 'Укажите email.',
            'email.email' => 'Введите корректный email-адрес.',
            'email.unique' => 'Пользователь с таким email уже зарегистрирован.',
            'password.required' => 'Укажите пароль.',
            'password.confirmed' => 'Пароли не совпадают.',
        ])->validate();

        $email = (string) ($input['email'] ?? '');
        $name = trim((string) ($input['name'] ?? ''));

        $user = User::create([
            'name' => Str::limit($name, 255, ''),
            'email' => Str::lower($email),
            'password' => Hash::make((string) ($input['password'] ?? '')),
        ]);

        $defaultRole = Role::where('slug', self::DEFAULT_ROLE_SLUG)->first();
        if ($defaultRole) {
            $user->roles()->attach($defaultRole->id);
        }

        return $user;
    }
}
