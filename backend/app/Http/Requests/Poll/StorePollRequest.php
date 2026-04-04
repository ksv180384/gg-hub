<?php

namespace App\Http\Requests\Poll;

use Domains\Guild\Models\Guild;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePollRequest extends FormRequest
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
            'created_by_character_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $this->route('guild')->id),
            ],
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
            'created_by_character_id.exists' => 'Выбранный персонаж не состоит в этой гильдии.',
            'ends_at.after' => 'Дата окончания должна быть в будущем.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'название',
            'description' => 'описание',
            'ends_at' => 'окончание',
            'options' => 'варианты ответа',
        ];
    }
}
