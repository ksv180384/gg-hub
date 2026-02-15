<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:games,slug'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите название игры.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'slug.required' => 'Введите slug (или оставьте пустым — подставится из названия).',
            'slug.max' => 'Slug не должен превышать 255 символов.',
            'slug.unique' => 'Игра с таким slug уже существует.',
            'image.image' => 'Файл должен быть изображением.',
            'image.mimes' => 'Допустимые форматы: jpeg, png, jpg, gif, webp.',
            'image.max' => 'Размер изображения не должен превышать 2 МБ.',
        ];
    }
}
