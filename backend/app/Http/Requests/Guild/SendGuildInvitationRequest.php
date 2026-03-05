<?php

namespace App\Http\Requests\Guild;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendGuildInvitationRequest extends FormRequest
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
            'character_id' => ['required', 'integer', 'exists:characters,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'character_id.required' => 'Укажите персонажа для приглашения.',
            'character_id.exists' => 'Персонаж не найден.',
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
            $character = Character::query()->with('game')->find($characterId);
            if (!$character) {
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
            if (GuildMember::query()->where('character_id', $characterId)->exists()) {
                $validator->errors()->add('character_id', 'Этот персонаж уже состоит в гильдии.');
                return;
            }
            if (GuildApplication::query()
                ->where('guild_id', $guild->id)
                ->where('character_id', $characterId)
                ->whereIn('status', ['pending', 'invitation'])
                ->exists()) {
                $validator->errors()->add('character_id', 'Этому персонажу уже отправлена заявка или приглашение в эту гильдию.');
                return;
            }
        });
    }
}
