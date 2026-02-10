<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuildRequest extends FormRequest
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
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'localization_id' => ['required', 'integer', 'exists:localizations,id'],
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
