<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuildMemberRoleRequest extends FormRequest
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
        $guild = $this->route('guild');
        $guildId = $guild?->id;

        return [
            'guild_role_id' => [
                'required',
                'integer',
                Rule::exists('guild_roles', 'id')->where('guild_id', $guildId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guild_role_id.required' => 'Выберите роль участника.',
            'guild_role_id.exists' => 'Указанная роль не найдена в этой гильдии.',
        ];
    }
}
