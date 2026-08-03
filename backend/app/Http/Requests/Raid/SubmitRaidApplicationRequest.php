<?php

namespace App\Http\Requests\Raid;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRaidApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['character_id' => ['required', 'integer']];
    }
}
