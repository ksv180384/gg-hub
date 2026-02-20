<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
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
            'slug' => ['nullable', 'string', 'max:255', 'unique:permissions,slug'],
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
            'slug.unique' => 'Право с таким слагом уже существует.',
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
