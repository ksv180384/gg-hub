<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuildApplicationCommentRequest extends FormRequest
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
        return [
            'body' => ['required', 'string', 'max:5000'],
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'parent_id' => ['nullable', 'integer', 'exists:guild_application_comments,id'],
        ];
    }
}
