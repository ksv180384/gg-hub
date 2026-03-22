<?php

namespace App\Http\Requests\Poll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePollRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_anonymous' => ['nullable', 'boolean'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'options' => ['required', 'array', 'min:2', 'max:20'],
            'options.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название голосования.',
            'title.max' => 'Название не должно превышать 255 символов.',
            'options.required' => 'Добавьте минимум 2 варианта ответа.',
            'options.min' => 'Добавьте минимум 2 варианта ответа.',
            'options.max' => 'Максимум 20 вариантов ответа.',
            'options.*.required' => 'Вариант ответа не может быть пустым.',
            'ends_at.after' => 'Дата окончания должна быть в будущем.',
        ];
    }
}
