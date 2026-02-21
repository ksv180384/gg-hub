<?php

namespace App\Http\Requests\Guild;

use Domains\Character\Models\Character;
use Domains\Guild\Models\GuildMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGuildRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'localization_id' => ['required', 'integer', 'exists:localizations,id'],
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'leader_character_id' => ['required', 'integer', 'exists:characters,id'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название гильдии.',
            'name.max' => 'Название гильдии не должно превышать 255 символов.',
            'localization_id.required' => 'Выберите локализацию.',
            'localization_id.exists' => 'Выбранная локализация не найдена.',
            'server_id.required' => 'Выберите сервер.',
            'server_id.exists' => 'Выбранный сервер не найден.',
            'leader_character_id.required' => 'Выберите лидера гильдии (персонажа на этом сервере).',
            'leader_character_id.exists' => 'Выбранный персонаж не найден.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $userId = $this->user()?->id;
            $serverId = (int) $this->input('server_id');
            $leaderId = (int) $this->input('leader_character_id');
            if (!$userId || !$leaderId) {
                return;
            }
            $character = Character::query()->find($leaderId);
            if (!$character) {
                return;
            }
            if ((int) $character->user_id !== $userId) {
                $validator->errors()->add('leader_character_id', 'Персонаж должен принадлежать вам.');
                return;
            }
            if ((int) $character->server_id !== $serverId) {
                $validator->errors()->add('leader_character_id', 'Персонаж должен находиться на том же сервере, что и гильдия.');
                return;
            }
            if (GuildMember::query()->where('character_id', $leaderId)->exists()) {
                $validator->errors()->add('leader_character_id', 'Этот персонаж уже состоит в гильдии. Персонаж может быть только в одной гильдии.');
            }
        });
    }
}
