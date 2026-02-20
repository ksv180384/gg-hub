<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'timezone' => ['nullable', 'string', 'max:50', 'timezone'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите имя.',
            'name.max' => 'Имя не должно превышать 255 символов.',
            'timezone.timezone' => 'Укажите корректный часовой пояс.',
            'avatar.image' => 'Файл должен быть изображением.',
            'avatar.mimes' => 'Допустимые форматы: jpeg, png, jpg, gif, webp.',
            'avatar.max' => 'Размер изображения не должен превышать 2 МБ.',
        ];
    }
}
