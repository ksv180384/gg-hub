<?php

namespace App\Http\Requests\Event;

use Domains\Event\Enums\EventRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'recurrence' => ['nullable', 'string', Rule::enum(EventRecurrence::class)],
            'recurrence_ends_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('ends_at', 'after_or_equal:starts_at', function ($input) {
            return ! empty($input->ends_at) && ! empty($input->starts_at);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название события.',
            'title.max' => 'Название не должно превышать 255 символов.',
            'starts_at.required' => 'Укажите дату и время начала события.',
            'ends_at.after_or_equal' => 'Дата окончания должна быть не раньше даты начала.',
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
            'starts_at' => 'начало',
            'ends_at' => 'окончание',
            'recurrence' => 'повторение',
            'recurrence_ends_at' => 'повторять до',
        ];
    }
}
