<?php

namespace App\Http\Requests\Access;

use Domains\Access\Models\PermissionGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $name = $this->input('name', '');
        $slugEmpty = !is_string($slug) || trim($slug) === '';
        if ($slugEmpty && is_string($name) && $name !== '') {
            $this->merge(['slug' => Str::slug($name)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $groupId = $this->input('permission_group_id');
        $group = is_numeric($groupId) ? PermissionGroup::find($groupId) : null;
        $scope = $group?->scope?->value ?? 'site';

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('permissions', 'slug')->where('scope', $scope),
            ],
            'description' => ['nullable', 'string'],
            'permission_group_id' => ['required', 'integer', 'exists:permission_groups,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите название права.',
            'name.string' => 'Название должно быть строкой.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'slug.string' => 'Слаг должен быть строкой.',
            'slug.max' => 'Слаг не должен превышать 255 символов.',
            'slug.unique' => 'Право с таким слагом уже существует в этой области (пользователи или гильдия). Выберите другое название или слаг.',
            'permission_group_id.required' => 'Выберите категорию прав.',
            'permission_group_id.integer' => 'Категория прав должна быть указана числом.',
            'permission_group_id.exists' => 'Указанная категория прав не найдена.',
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
