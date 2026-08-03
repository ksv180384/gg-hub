<?php

namespace App\Http\Requests\Raid;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRaidRecruitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_recruiting' => ['required', 'boolean']];
    }
}
