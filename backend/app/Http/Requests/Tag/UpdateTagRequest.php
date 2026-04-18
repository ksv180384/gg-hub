<?php

namespace App\Http\Requests\Tag;

use Domains\Tag\Models\Tag;
use Domains\Tag\Rules\UniqueTagNameForCreatorUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
        if ($this->has('slug')) {
            $this->merge([
                'slug' => trim((string) $this->input('slug')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Tag $tag */
        $tag = $this->route('tag');
        $creatorId = $tag->created_by_user_id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                new UniqueTagNameForCreatorUser(
                    $creatorId !== null ? (int) $creatorId : null,
                    (int) $tag->id,
                ),
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'is_hidden' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название тега.',
            'name.max' => 'Название тега не должно превышать 20 символов.',
            'slug.max' => 'Слаг не должен превышать 255 символов.',
        ];
    }
}
