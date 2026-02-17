<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesPermissionsRequest extends FormRequest
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
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_ids.array' => 'Список ролей должен быть массивом.',
            'role_ids.*.integer' => 'Каждый идентификатор роли должен быть числом.',
            'role_ids.*.exists' => 'Одна или несколько указанных ролей не найдены.',
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
            'role_ids' => 'роли',
            'permission_ids' => 'права',
        ];
    }
}
