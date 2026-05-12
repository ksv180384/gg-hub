<?php

namespace App\Http\Requests\GuildBank;

use Domains\Guild\Models\Guild;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuildBankItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Guild $guild */
        $guild = $this->route('guild');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'guild_bank_item_tier_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_bank_item_tiers', 'id')->where('guild_id', $guild->id),
            ],
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
            'guild_bank_item_tier_id.integer' => 'Тир предмета должен быть числом.',
            'guild_bank_item_tier_id.exists' => 'Выбранный тир не найден в этой гильдии.',
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
            'guild_bank_item_tier_id' => 'тир',
            'dkp_cost' => 'стоимость в ДКП',
            'quantity' => 'количество',
        ];
    }
}

