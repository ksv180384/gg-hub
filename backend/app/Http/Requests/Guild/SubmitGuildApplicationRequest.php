<?php

namespace App\Http\Requests\Guild;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildMember;
use Illuminate\Foundation\Http\FormRequest;
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
                if ($field->required) {
                    $rules[$key] = ['required', 'string', 'max:65535'];
                } else {
                    $rules[$key] = ['nullable', 'string', 'max:65535'];
                }
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
        });
    }
}
