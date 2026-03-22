<?php

namespace App\Http\Requests\Poll;

use Domains\Guild\Models\Guild;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawVotePollRequest extends FormRequest
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

        return [
            'character_id' => [
                'required',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild instanceof Guild ? $guild->id : null),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'character_id.required' => 'Укажите персонажа.',
            'character_id.exists' => 'Персонаж не состоит в этой гильдии.',
        ];
    }
}
