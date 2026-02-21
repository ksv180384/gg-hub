<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guild = $this->route('guild');
        return $guild && $this->user() && (int) $guild->owner_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'localization_id' => ['sometimes', 'required', 'integer', 'exists:localizations,id'],
            'server_id' => ['sometimes', 'required', 'integer', 'exists:servers,id'],
            'show_roster_to_all' => ['sometimes', 'boolean'],
            'about_text' => ['nullable', 'string'],
            'charter_text' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название гильдии.',
            'name.max' => 'Название гильдии не должно превышать 255 символов.',
            'localization_id.required' => 'Выберите локализацию.',
            'localization_id.exists' => 'Выбранная локализация не найдена.',
            'server_id.required' => 'Выберите сервер.',
            'server_id.exists' => 'Выбранный сервер не найден.',
            'logo.image' => 'Логотип должен быть изображением (jpeg, png, gif и т.д.).',
            'logo.max' => 'Размер файла логотипа не должен превышать 2 МБ.',
        ];
    }
}
