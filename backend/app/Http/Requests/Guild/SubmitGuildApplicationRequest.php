<?php

namespace App\Http\Requests\Guild;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitGuildApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guild = $this->route('guild');
        if ($guild instanceof Guild && !$guild->relationLoaded('applicationFormFields')) {
            $guild->load('applicationFormFields');
        }
        $rules = [
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'form_data' => ['required', 'array'],
        ];

        if ($guild instanceof Guild && $guild->relationLoaded('applicationFormFields')) {
            foreach ($guild->applicationFormFields as $field) {
                $key = 'form_data.' . $field->id;
                $base = $field->required ? ['required', 'string', 'max:65535'] : ['nullable', 'string', 'max:65535'];
                if ($field->type === 'select' && !empty($field->options)) {
                    $base[] = Rule::in($field->options);
                }
                $rules[$key] = $base;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'character_id.required' => 'Выберите персонажа для заявки.',
            'character_id.exists' => 'Выбранный персонаж не найден.',
            'form_data.required' => 'Заполните поля формы заявки.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $guild = $this->route('guild');
            if (!$user || !$guild instanceof Guild) {
                return;
            }
            $characterId = (int) $this->input('character_id');
            $character = Character::query()->find($characterId);
            if (!$character) {
                return;
            }
            if ((int) $character->user_id !== (int) $user->id) {
                $validator->errors()->add('character_id', 'Персонаж должен принадлежать вам.');
                return;
            }
            if ($character->guildMember()->exists()) {
                $validator->errors()->add('character_id', 'Этот персонаж уже состоит в гильдии.');
                return;
            }
            if ((int) $character->game_id !== (int) $guild->game_id) {
                $validator->errors()->add('character_id', 'Персонаж должен быть из той же игры, что и гильдия.');
                return;
            }
            if ((int) $character->server_id !== (int) $guild->server_id) {
                $validator->errors()->add('character_id', 'Персонаж должен быть на том же сервере, что и гильдия.');
                return;
            }
            if (!$guild->is_recruiting) {
                $validator->errors()->add('guild', 'В данную гильдию сейчас закрыт набор.');
                return;
            }
            if (GuildApplication::query()->where('guild_id', $guild->id)->where('character_id', $characterId)->whereIn('status', ['pending'])->exists()) {
                $validator->errors()->add('character_id', 'Вы уже подали заявку в эту гильдию с этим персонажем.');
                return;
            }
            // Валидация multiselect: значение — JSON-массив, каждый элемент из options
            if (!$guild->relationLoaded('applicationFormFields')) {
                $guild->load('applicationFormFields');
            }
            foreach ($guild->applicationFormFields as $field) {
                if ($field->type !== 'multiselect' || empty($field->options)) {
                    continue;
                }
                $value = $this->input('form_data.' . $field->id);
                if ($value === null || $value === '') {
                    if ($field->required) {
                        $validator->errors()->add('form_data.' . $field->id, 'Выберите хотя бы один вариант для поля «' . $field->name . '».');
                    }
                    continue;
                }
                $decoded = json_decode($value, true);
                if (!is_array($decoded)) {
                    $validator->errors()->add('form_data.' . $field->id, 'Некорректное значение для поля «' . $field->name . '».');
                    continue;
                }
                $optionsSet = array_flip($field->options);
                foreach ($decoded as $item) {
                    if (!is_string($item) || !isset($optionsSet[$item])) {
                        $validator->errors()->add('form_data.' . $field->id, 'Выбран недопустимый вариант для поля «' . $field->name . '».');
                        break;
                    }
                }
            }
        });
    }
}
