<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCharacterRequest extends FormRequest
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
        $localizationId = $this->input('localization_id');
        $characterId = $this->route('character');
        $character = is_numeric($characterId)
            ? \Domains\Character\Models\Character::with('game')->find($characterId)
            : null;
        $game = $character?->game;
        $maxClasses = $game ? (int) $game->max_classes_per_character : 1;
        $gameId = $game?->id;

        $rules = [
            'localization_id' => ['required', 'integer', 'exists:localizations,id'],
            'server_id' => [
                'required',
                'integer',
                Rule::exists('servers', 'id')->where('localization_id', $localizationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
            'game_class_ids' => ['nullable', 'array', 'max:' . $maxClasses],
        ];
        if ($gameId) {
            $rules['game_class_ids.*'] = ['integer', Rule::exists('game_classes', 'id')->where('game_id', $gameId)];
        }
        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'localization_id.required' => 'Выберите локализацию.',
            'localization_id.integer' => 'Локализация должна быть указана числом.',
            'localization_id.exists' => 'Выбранная локализация не найдена.',
            'server_id.required' => 'Выберите сервер.',
            'server_id.integer' => 'Сервер должен быть указан числом.',
            'server_id.exists' => 'Выбранный сервер не найден или не относится к выбранной локализации.',
            'name.required' => 'Введите имя персонажа.',
            'name.string' => 'Имя должно быть строкой.',
            'name.max' => 'Имя не должно превышать 255 символов.',
            'avatar.image' => 'Файл аватара должен быть изображением (JPEG, PNG, GIF или WebP).',
            'avatar.mimes' => 'Аватар должен быть в формате JPEG, PNG, GIF или WebP.',
            'avatar.max' => 'Размер файла аватара не должен превышать 2 МБ.',
            'game_class_ids.array' => 'Классы должны быть указаны списком.',
            'game_class_ids.max' => 'Можно выбрать не более :max классов для персонажа.',
            'game_class_ids.*.integer' => 'Каждый класс должен быть указан числом.',
            'game_class_ids.*.exists' => 'Один из выбранных классов не найден или не относится к этой игре.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'localization_id' => 'локализация',
            'server_id' => 'сервер',
            'name' => 'имя персонажа',
            'avatar' => 'аватар',
            'remove_avatar' => 'удалить аватар',
            'game_class_ids' => 'классы',
        ];
    }
}
