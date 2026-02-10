<?php

namespace App\Http\Requests\Character;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterRequest extends FormRequest
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
            'class' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
