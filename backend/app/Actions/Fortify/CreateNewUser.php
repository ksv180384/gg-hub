<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Domains\Access\Models\Role;
use Domains\User\Actions\ResolveRegistrationUserNameFromEmailAction;
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
        $nameInput = trim((string) ($input['name'] ?? ''));

        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ];

        if ($nameInput !== '') {
            $rules['name'][] = Rule::unique(User::class, 'name');
        }

        Validator::make($input, $rules, [
            'name.unique' => 'Пользователь с таким именем уже зарегистрирован. Выберите другое имя.',
            'name.max' => 'Имя не должно быть длиннее 255 символов.',
            'email.required' => 'Укажите email.',
            'email.email' => 'Введите корректный email-адрес.',
            'email.unique' => 'Пользователь с таким email уже зарегистрирован.',
            'password.required' => 'Укажите пароль.',
            'password.confirmed' => 'Пароли не совпадают.',
        ])->validate();

        $email = Str::lower((string) ($input['email'] ?? ''));

        $name = $nameInput !== ''
            ? Str::limit($nameInput, 255, '')
            : app(ResolveRegistrationUserNameFromEmailAction::class)($email);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make((string) ($input['password'] ?? '')),
        ]);

        $defaultRole = Role::where('slug', self::DEFAULT_ROLE_SLUG)->first();
        if ($defaultRole) {
            $user->roles()->attach($defaultRole->id);
        }

        return $user;
    }
}
