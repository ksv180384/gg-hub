<?php

namespace App\Http\Requests\Guild;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guild = $this->route('guild');
        return $guild && $this->user() && (int) $guild->owner_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'localization_id' => ['sometimes', 'required', 'integer', 'exists:localizations,id'],
            'server_id' => ['sometimes', 'required', 'integer', 'exists:servers,id'],
            'show_roster_to_all' => ['sometimes', 'boolean'],
            'about_text' => ['nullable', 'string'],
            'charter_text' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
            'leader_character_id' => ['sometimes', 'required', 'integer', 'exists:characters,id'],
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
            'name.required' => 'Укажите название гильдии.',
            'name.max' => 'Название гильдии не должно превышать 255 символов.',
            'localization_id.required' => 'Выберите локализацию.',
            'localization_id.exists' => 'Выбранная локализация не найдена.',
            'server_id.required' => 'Выберите сервер.',
            'server_id.exists' => 'Выбранный сервер не найден.',
            'logo.image' => 'Логотип должен быть изображением (jpeg, png, gif и т.д.).',
            'logo.max' => 'Размер файла логотипа не должен превышать 2 МБ.',
            'leader_character_id.required' => 'Укажите лидера гильдии (персонажа на этом сервере).',
            'leader_character_id.exists' => 'Выбранный персонаж не найден.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (!$this->has('leader_character_id')) {
                return;
            }
            /** @var \Domains\Guild\Models\Guild $guild */
            $guild = $this->route('guild');
            $userId = $this->user()?->id;
            $serverId = (int) ($this->input('server_id') ?? $guild->server_id);
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
            if (GuildMember::query()->where('character_id', $leaderId)->where('guild_id', '!=', $guild->id)->exists()) {
                $validator->errors()->add('leader_character_id', 'Этот персонаж уже состоит в другой гильдии. Персонаж может быть только в одной гильдии.');
                return;
            }
            if (Guild::query()->where('leader_character_id', $leaderId)->where('id', '!=', $guild->id)->exists()) {
                $validator->errors()->add('leader_character_id', 'Этот персонаж уже является лидером другой гильдии. Лидером может быть только персонаж, который не возглавляет другую гильдию.');
            }
        });
    }
}
