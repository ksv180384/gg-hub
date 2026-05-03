<?php

namespace App\Http\Requests\Landing;

use Illuminate\Foundation\Http\FormRequest;

class StoreLandingCtaClickRequest extends FormRequest
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
            'button' => ['required', 'string', 'in:start_free,create_account'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'button.required' => 'Укажите идентификатор кнопки.',
            'button.in' => 'Недопустимое значение кнопки.',
        ];
    }
}
