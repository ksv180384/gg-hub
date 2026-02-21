<?php

namespace App\Http\Requests\GameClass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $gameClass = $this->route('game_class');

        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ru' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('game_classes', 'slug')
                    ->where('game_id', $gameClass->game_id)
                    ->ignore($gameClass->id),
            ],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
