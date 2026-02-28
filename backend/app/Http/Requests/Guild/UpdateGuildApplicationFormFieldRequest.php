<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuildApplicationFormFieldRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(['text', 'textarea', 'screenshot', 'select', 'multiselect'])],
            'required' => ['sometimes', 'boolean'],
            'options' => [
                'required_if:type,select,multiselect',
                'array',
                'min:1',
            ],
            'options.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название поля.',
            'name.max' => 'Название поля не должно превышать 255 символов.',
            'type.required' => 'Выберите тип поля.',
            'type.in' => 'Недопустимый тип поля. Допустимые: текст, большой текст, скриншот, выбор одного варианта, выбор нескольких вариантов.',
            'options.required_if' => 'Для полей «Выбор» и «Мультивыбор» добавьте хотя бы один вариант выбора.',
            'options.*.max' => 'Вариант выбора не должен превышать 255 символов.',
        ];
    }
}
