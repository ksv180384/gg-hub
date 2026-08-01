<?php

namespace App\Http\Requests\ConstantParty;

use Domains\ConstantParty\Models\ConstantParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstantPartyStorageGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ConstantParty $constantParty */
        $constantParty = $this->route('constant_party');

        return [
            'item_id' => [
                'required',
                'integer',
                Rule::exists('constant_party_storage_items', 'id')->where('constant_party_id', $constantParty->id),
            ],
            'received_by_character_id' => [
                'required',
                'integer',
                Rule::exists('constant_party_members', 'character_id')->where('constant_party_id', $constantParty->id),
            ],
            'granted_by_character_id' => [
                'required',
                'integer',
                Rule::exists('constant_party_members', 'character_id')->where('constant_party_id', $constantParty->id),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'granted_at' => ['nullable', 'date'],
        ];
    }
}
