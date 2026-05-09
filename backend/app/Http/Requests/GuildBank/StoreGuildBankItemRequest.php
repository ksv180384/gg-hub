<?php

namespace App\Http\Requests\GuildBank;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuildBankItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tier' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:30'],
            'dkp_cost' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название предмета.',
            'name.max' => 'Название предмета не должно превышать 255 символов.',
            'description.max' => 'Описание не должно превышать 5000 символов.',
            'tier.max' => 'Тир не должен превышать 50 символов.',
            'color.max' => 'Цвет не должен превышать 30 символов.',
            'dkp_cost.integer' => 'Стоимость в ДКП должна быть числом.',
            'dkp_cost.min' => 'Стоимость в ДКП не может быть отрицательной.',
            'quantity.integer' => 'Количество должно быть числом.',
            'quantity.min' => 'Количество не может быть отрицательным.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'description' => 'описание',
            'tier' => 'тир',
            'color' => 'цвет',
            'dkp_cost' => 'стоимость в ДКП',
            'quantity' => 'количество',
        ];
    }
}

