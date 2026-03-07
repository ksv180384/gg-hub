<?php

namespace App\Http\Requests\Raid;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetRaidCompositionRequest extends FormRequest
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
            'members' => ['required', 'array'],
            'members.*.character_id' => [
                'required',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'members.*.slot_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
