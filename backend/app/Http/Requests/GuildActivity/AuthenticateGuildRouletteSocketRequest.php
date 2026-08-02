<?php

declare(strict_types=1);

namespace App\Http\Requests\GuildActivity;

use Illuminate\Foundation\Http\FormRequest;

class AuthenticateGuildRouletteSocketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:2048'],
        ];
    }
}
