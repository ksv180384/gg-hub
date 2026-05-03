<?php

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-параметры списка тегов (GET /tags): фильтры админки и служебные include_hidden, guild_id.
 */
class TagListFilterRequest extends FormRequest
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
            'include_hidden' => ['sometimes'],
            'guild_id' => ['nullable', 'integer', 'min:1'],
            'kind' => ['nullable', 'string', Rule::in(['common', 'guild', 'user'])],
            'tag_name' => ['nullable', 'string', 'max:255'],
            'guild_name' => ['nullable', 'string', 'max:255'],
            'user_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
