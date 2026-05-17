<?php

namespace App\Http\Requests\GuildDkp;

use Illuminate\Foundation\Http\FormRequest;

class AdjustGuildUserDkpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'not_in:0', 'min:-1000000000', 'max:1000000000'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'Укажите изменение ДКП.',
            'amount.integer' => 'Изменение ДКП должно быть целым числом.',
            'amount.not_in' => 'Изменение ДКП не может быть равно нулю.',
            'amount.min' => 'Изменение ДКП слишком мало.',
            'amount.max' => 'Изменение ДКП слишком велико.',
            'reason.max' => 'Комментарий не должен превышать 5000 символов.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'amount' => 'изменение ДКП',
            'reason' => 'комментарий',
        ];
    }
}
