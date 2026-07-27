<?php

namespace App\Http\Requests\ConstantParty;

use Illuminate\Foundation\Http\FormRequest;

class InviteConstantPartyCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'invited_by_character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
