<?php

namespace App\Http\Requests\ConstantParty;

use Illuminate\Foundation\Http\FormRequest;

class StoreConstantPartyChatMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
