<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
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
        $permission = $this->route('permission');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('permissions', 'slug')->ignore($permission),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'permission_group_id' => ['sometimes', 'required', 'integer', 'exists:permission_groups,id'],
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
            'permission_group_id' => 'категория прав',
        ];
    }
}
