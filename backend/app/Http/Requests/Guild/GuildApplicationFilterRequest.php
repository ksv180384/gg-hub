<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuildApplicationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in(['pending', 'invitation', 'approved', 'rejected', 'revoked', 'withdrawn']),
            ],
            'character_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Неверный статус заявки.',
            'character_name.max' => 'Имя не должно превышать 255 символов.',
        ];
    }
}

