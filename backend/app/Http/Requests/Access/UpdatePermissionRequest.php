<?php

namespace App\Http\Requests\Access;

use Domains\Access\Models\PermissionGroup;
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
        $groupId = $this->input('permission_group_id');
        if (is_numeric($groupId)) {
            $group = PermissionGroup::find($groupId);
            $scope = $group?->scope?->value ?? $permission?->scope?->value ?? 'site';
        } else {
            $scope = $permission?->scope?->value ?? 'site';
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('permissions', 'slug')->where('scope', $scope)->ignore($permission),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'permission_group_id' => ['sometimes', 'required', 'integer', 'exists:permission_groups,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Право с таким слагом уже существует в этой области. Выберите другое название или слаг.',
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
