<?php

namespace App\Http\Requests\ConstantParty;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConstantPartyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'can_manage_storage' => ['required', 'boolean'],
        ];
    }
}
