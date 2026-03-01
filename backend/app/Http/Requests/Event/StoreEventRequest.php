<?php

namespace App\Http\Requests\Event;

use Domains\Event\Enums\EventRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
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
        $guild = $this->route('guild');

        return [
            'character_id' => [
                'required',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'recurrence' => ['nullable', 'string', Rule::enum(EventRecurrence::class)],
            'recurrence_ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'character_id.required' => 'Укажите персонажа от имени которого создаётся событие.',
            'character_id.exists' => 'Выбранный персонаж не состоит в этой гильдии.',
            'title.required' => 'Укажите название события.',
            'title.max' => 'Название не должно превышать 255 символов.',
            'starts_at.required' => 'Укажите дату и время начала события.',
            'starts_at.date' => 'Дата начала должна быть в формате даты и времени.',
            'ends_at.after_or_equal' => 'Дата окончания должна быть не раньше даты начала.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'character_id' => 'персонаж',
            'title' => 'название',
            'description' => 'описание',
            'starts_at' => 'начало',
            'ends_at' => 'окончание',
            'recurrence' => 'повторение',
            'recurrence_ends_at' => 'повторять до',
        ];
    }
}
