<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CharacterFilterRequest extends FormRequest
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
        $game = $this->route('game');
        $gameId = $game?->id;

        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'localization_ids' => ['nullable', 'array'],
            'localization_ids.*' => ['integer', 'exists:localizations,id'],
            'server_ids' => ['nullable', 'array'],
            'server_ids.*' => ['integer', 'exists:servers,id'],
            'game_class_ids' => ['nullable', 'array'],
        ];

        if ($gameId !== null) {
            $rules['game_class_ids.*'] = [
                'integer',
                Rule::exists('game_classes', 'id')->where('game_id', $gameId),
            ];
        } else {
            $rules['game_class_ids.*'] = ['integer', 'exists:game_classes,id'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Имя не должно превышать 255 символов.',
            'localization_ids.*.exists' => 'Одна из выбранных локализаций не найдена.',
            'server_ids.*.exists' => 'Один из выбранных серверов не найден.',
            'game_class_ids.*.exists' => 'Один из выбранных классов не найден или не принадлежит этой игре.',
        ];
    }
}
