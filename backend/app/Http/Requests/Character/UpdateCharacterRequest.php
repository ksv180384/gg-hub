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
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'game_class_ids' => ['nullable', 'array', 'max:' . $maxClasses],
        ];
        if ($gameId) {
            $rules['game_class_ids.*'] = ['integer', Rule::exists('game_classes', 'id')->where('game_id', $gameId)];
        }
        return $rules;
    }
}
