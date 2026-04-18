<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuildRosterMemberTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('tag_ids');
        $this->merge([
            'tag_ids' => is_array($raw) ? $raw : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tag_ids.array' => 'Список тегов должен быть массивом.',
            'tag_ids.*.integer' => 'Каждый тег должен быть указан числом.',
            'tag_ids.*.exists' => 'Один или несколько тегов не найдены.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tag_ids' => 'теги',
        ];
    }
}
