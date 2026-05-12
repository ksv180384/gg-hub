<?php

namespace App\Http\Requests\GuildBank;

use Domains\Guild\Models\Guild;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuildBankItemTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }

        if ($this->has('color')) {
            $this->merge([
                'color' => trim((string) $this->input('color')),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Guild $guild */
        $guild = $this->route('guild');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('guild_bank_item_tiers', 'name')->where('guild_id', $guild->id),
            ],
            'color' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название тира.',
            'name.max' => 'Название тира не должно превышать 50 символов.',
            'name.unique' => 'Тир с таким названием уже существует в этой гильдии.',
            'color.required' => 'Укажите цвет тира.',
            'color.max' => 'Цвет не должен превышать 30 символов.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'название тира',
            'color' => 'цвет тира',
        ];
    }
}
