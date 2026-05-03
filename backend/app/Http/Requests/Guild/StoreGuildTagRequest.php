<?php

namespace App\Http\Requests\Guild;

use Domains\Guild\Models\Guild;
use Domains\Tag\Rules\UniqueTagNameForGuild;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuildTagRequest extends FormRequest
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
        /** @var Guild $guild */
        $guild = $this->route('guild');

        return [
            'name' => ['required', 'string', 'max:20', new UniqueTagNameForGuild((int) $guild->id)],
            'slug' => ['nullable', 'string', 'max:255'],
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
