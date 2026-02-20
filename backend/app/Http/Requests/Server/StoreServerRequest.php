<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $localization = $this->route('localization');
        $gameId = $localization?->game_id;
        $localizationId = $localization?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('servers', 'slug')
                    ->where('game_id', $gameId)
                    ->where('localization_id', $localizationId),
            ],
            'is_active' => ['sometimes', 'boolean'],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Введите название сервера.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'slug.required' => 'Введите слаг (латиница, цифры, дефис).',
            'slug.unique' => 'Сервер с таким слагом уже есть в этой локализации.',
            'slug.max' => 'Слаг не должен превышать 255 символов.',
        ];
    }
}
