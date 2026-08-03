<?php

namespace App\Http\Requests\ConstantParty;

use Domains\ConstantParty\Models\ConstantParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConstantPartyStorageItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tier_id' => [
                'nullable',
                'integer',
                Rule::exists('constant_party_storage_item_tiers', 'id')->where('constant_party_id', $constantParty->id),
            ],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'actor_character_id' => ['required', 'integer', 'exists:characters,id'],
        ];
    }
}
