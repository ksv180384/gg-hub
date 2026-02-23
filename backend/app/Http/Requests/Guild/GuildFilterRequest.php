<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;

class GuildFilterRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'game_id' => ['nullable', 'integer', 'exists:games,id'],
            'localization_ids' => ['nullable', 'array'],
            'localization_ids.*' => ['integer', 'exists:localizations,id'],
            'server_ids' => ['nullable', 'array'],
            'server_ids.*' => ['integer', 'exists:servers,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Название не должно превышать 255 символов.',
            'game_id.exists' => 'Выбранная игра не найдена.',
            'localization_ids.*.exists' => 'Одна из выбранных локализаций не найдена.',
            'server_ids.*.exists' => 'Один из выбранных серверов не найден.',
            'per_page.min' => 'Количество на странице должно быть не менее 1.',
            'per_page.max' => 'Количество на странице не должно превышать 100.',
        ];
    }
}
