<?php

namespace App\Http\Requests\Character;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterRequest extends FormRequest
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
        $gameId = (int) $this->input('game_id');
        $localizationId = $this->input('localization_id');
        $game = $gameId ? Game::find($gameId) : null;
        $maxClasses = $game ? (int) $game->max_classes_per_character : 1;

        return [
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'localization_id' => [
                'required',
                'integer',
                Rule::exists('localizations', 'id')->where('game_id', $gameId),
            ],
            'server_id' => [
                'required',
                'integer',
                Rule::exists('servers', 'id')->where('localization_id', $localizationId),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('characters')->where('server_id', $this->input('server_id')),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'game_class_ids' => ['nullable', 'array', 'max:' . $maxClasses],
            'game_class_ids.*' => ['integer', Rule::exists('game_classes', 'id')->where('game_id', $gameId)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'game_id.required' => 'Укажите игру.',
            'game_id.integer' => 'Игра должна быть указана числом.',
            'game_id.exists' => 'Выбранная игра не найдена.',
            'localization_id.required' => 'Выберите локализацию.',
            'localization_id.integer' => 'Локализация должна быть указана числом.',
            'localization_id.exists' => 'Выбранная локализация не найдена или не относится к этой игре.',
            'server_id.required' => 'Выберите сервер.',
            'server_id.integer' => 'Сервер должен быть указан числом.',
            'server_id.exists' => 'Выбранный сервер не найден или не относится к выбранной локализации.',
            'name.required' => 'Введите имя персонажа.',
            'name.string' => 'Имя должно быть строкой.',
            'name.max' => 'Имя не должно превышать 255 символов.',
            'name.unique' => 'На этом сервере уже есть персонаж с таким именем. Выберите другое имя.',
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
            'game_id' => 'игра',
            'localization_id' => 'локализация',
            'server_id' => 'сервер',
            'name' => 'имя персонажа',
            'avatar' => 'аватар',
            'game_class_ids' => 'классы',
        ];
    }
}
