<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuildMemberDkpCoefficientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dkp_coefficient' => ['required', 'numeric', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dkp_coefficient.required' => 'Укажите коэффициент ДКП.',
            'dkp_coefficient.numeric' => 'Коэффициент ДКП должен быть числом.',
            'dkp_coefficient.min' => 'Коэффициент ДКП не может быть отрицательным.',
            'dkp_coefficient.max' => 'Коэффициент ДКП слишком большой.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dkp_coefficient' => 'коэффициент ДКП',
        ];
    }
}
