<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:10000'],
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'parent_id' => ['nullable', 'integer', 'exists:post_comments,id'],
        ];
    }
}
