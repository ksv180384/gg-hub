<?php

namespace App\Http\Requests\Poll;

use Domains\Guild\Models\Guild;
use Domains\Poll\Models\Poll;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('is_anonymous')) {
                return;
            }

            $guild = $this->route('guild');
            $pollId = $this->route('poll');

            if (! $guild instanceof Guild || ! is_numeric($pollId)) {
                return;
            }

            $poll = Poll::query()
                ->where('guild_id', $guild->getKey())
                ->whereKey((int) $pollId)
                ->first(['id', 'is_anonymous']);

            if ($poll === null || ! $poll->is_anonymous) {
                return;
            }

            if (! $this->boolean('is_anonymous')) {
                $validator->errors()->add(
                    'is_anonymous',
                    'Нельзя отключить анонимность: голосование создано как анонимное.'
                );
            }
        });
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
            'is_anonymous.boolean' => 'Укажите корректное значение для режима анонимности.',
        ];
    }
}
