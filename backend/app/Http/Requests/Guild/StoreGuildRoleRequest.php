<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreGuildRoleRequest extends FormRequest
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
        $guildId = $this->route('guild')?->id;
        $slugRule = $guildId
            ? Rule::unique('guild_roles', 'slug')->where('guild_id', $guildId)
            : ['nullable', 'string', 'max:255'];
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите название роли.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'slug.unique' => 'Роль с таким слагом уже есть в гильдии.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $name = $this->input('name', '');
        if ((!is_string($slug) || trim($slug) === '') && is_string($name) && $name !== '') {
            $this->merge(['slug' => Str::slug($name)]);
        }
    }
}
