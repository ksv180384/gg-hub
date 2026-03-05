<?php

namespace App\Http\Requests\Raid;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRaidRequest extends FormRequest
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
        $guild = $this->route('guild');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('raids', 'id')->where('guild_id', $guild->id),
            ],
            'leader_character_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название рейда.',
            'name.max' => 'Название не должно превышать 255 символов.',
            'leader_character_id.exists' => 'Выбранный персонаж не состоит в этой гильдии.',
            'parent_id.exists' => 'Родительский рейд не найден или принадлежит другой гильдии.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название',
            'description' => 'описание',
            'parent_id' => 'родительский рейд',
            'leader_character_id' => 'лидер',
            'sort_order' => 'порядок',
        ];
    }
}
