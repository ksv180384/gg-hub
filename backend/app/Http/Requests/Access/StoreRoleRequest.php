<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите название роли.',
            'name.string' => 'Название должно быть строкой.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'slug.string' => 'Слаг должен быть строкой.',
            'slug.max' => 'Слаг не должен превышать 255 символов.',
            'slug.unique' => 'Роль с таким слагом уже существует.',
            'permission_ids.array' => 'Список прав должен быть массивом.',
            'permission_ids.*.integer' => 'Каждый идентификатор права должен быть числом.',
            'permission_ids.*.exists' => 'Одно или несколько указанных прав не найдены.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'slug' => 'слаг',
            'description' => 'описание',
            'permission_ids' => 'права',
        ];
    }
}
