<?php

namespace App\Http\Requests\GuildBank;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuildBankGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $guild = $this->route('guild');

        return [
            'guild_bank_item_id' => [
                'required',
                'integer',
                Rule::exists('guild_bank_items', 'id')->where('guild_id', $guild->id),
            ],
            'received_by_character_id' => [
                'required',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'granted_by_character_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'reason' => ['nullable', 'string', 'max:5000'],
            'granted_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'guild_bank_item_id.required' => 'Укажите предмет.',
            'guild_bank_item_id.exists' => 'Указанный предмет не найден в банке этой гильдии.',
            'received_by_character_id.required' => 'Укажите участника, который получил предмет.',
            'received_by_character_id.exists' => 'Выбранный персонаж не состоит в этой гильдии.',
            'granted_by_character_id.exists' => 'Указанный персонаж, выдавший предмет, не состоит в этой гильдии.',
            'reason.max' => 'Поле «за что» не должно превышать 5000 символов.',
            'granted_at.date' => 'Дата выдачи должна быть в формате даты и времени.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'guild_bank_item_id' => 'предмет',
            'received_by_character_id' => 'участник',
            'granted_by_character_id' => 'кто выдал',
            'reason' => 'за что',
            'granted_at' => 'дата выдачи',
        ];
    }
}

