<?php

namespace App\Http\Requests\EventHistory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventHistoryTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $title = $this->route('eventHistoryTitle');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('event_history_titles', 'name')->ignore($title?->id),
            ],
            'dkp_base_points' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название вида события.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'name.unique' => 'Такое название уже существует. Введите другое название.',
            'dkp_base_points.integer' => 'Очки ДКП должны быть числом.',
            'dkp_base_points.min' => 'Очки ДКП не могут быть отрицательными.',
            'dkp_base_points.max' => 'Слишком большое значение очков ДКП.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'dkp_base_points' => 'очки ДКП',
        ];
    }
}
