<?php

namespace App\Http\Requests\ConstantParty;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConstantPartyChatReceiptsRequest extends FormRequest
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
            'message_ids' => ['required', 'array', 'min:1', 'max:100'],
            'message_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
