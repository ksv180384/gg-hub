<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuildRolePermissionsRequest extends FormRequest
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
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permission_ids.present' => 'Не переданы права роли. Отправьте массив permission_ids (может быть пустым).',
            'permission_ids.array' => 'Поле прав роли должно быть массивом идентификаторов.',
            'permission_ids.*.integer' => 'Каждый элемент списка прав должен быть числом.',
            'permission_ids.*.exists' => 'Указано несуществующее право. Обновите страницу и попробуйте снова.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'permission_ids' => 'права роли',
        ];
    }
}
