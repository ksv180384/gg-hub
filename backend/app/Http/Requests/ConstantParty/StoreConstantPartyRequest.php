<?php

namespace App\Http\Requests\ConstantParty;

use Illuminate\Foundation\Http\FormRequest;

class StoreConstantPartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'leader_character_id' => ['required', 'integer', 'exists:characters,id'],
        ];
    }
}
