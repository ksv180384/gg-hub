<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionGroupRequest extends FormRequest
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
        $scope = $this->input('scope', 'site');
        return [
            'scope' => ['sometimes', 'string', 'in:site,guild'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('permission_groups', 'slug')->where('scope', $scope),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => 'Укажите область прав (site или guild).',
            'scope.in' => 'Область прав должна быть site или guild.',
            'name.required' => 'Введите название категории прав.',
            'name.string' => 'Название должно быть строкой.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'slug.string' => 'Слаг должен быть строкой.',
            'slug.max' => 'Слаг не должен превышать 255 символов.',
            'slug.unique' => 'Категория с таким слагом уже существует в этой области.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scope' => 'область прав',
            'name' => 'название',
            'slug' => 'слаг',
        ];
    }
}
