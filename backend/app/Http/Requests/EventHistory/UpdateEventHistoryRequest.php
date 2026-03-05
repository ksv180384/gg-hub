<?php

namespace App\Http\Requests\EventHistory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventHistoryRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],

            'participants' => ['sometimes', 'nullable', 'array'],
            'participants.*.character_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'participants.*.external_name' => ['nullable', 'string', 'max:255'],

            'screenshots' => ['sometimes', 'nullable', 'array'],
            'screenshots.*.url' => ['required_with:screenshots', 'url', 'max:2000'],
            'screenshots.*.title' => ['nullable', 'string', 'max:255'],
            'screenshots.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название события.',
            'title.max' => 'Название не должно превышать 255 символов.',
            'description.max' => 'Описание не должно превышать 5000 символов.',
            'occurred_at.date' => 'Дата и время проведения должны быть корректной датой.',

            'participants.array' => 'Список участников должен быть массивом.',
            'participants.*.character_id.integer' => 'Участник должен быть корректным персонажем.',
            'participants.*.character_id.exists' => 'Выбранный персонаж не состоит в этой гильдии.',
            'participants.*.external_name.max' => 'Ник участника не должен превышать 255 символов.',

            'screenshots.array' => 'Список скриншотов должен быть массивом.',
            'screenshots.*.url.required_with' => 'Укажите ссылку на скриншот.',
            'screenshots.*.url.url' => 'Ссылка на скриншот должна быть корректным URL.',
            'screenshots.*.url.max' => 'Ссылка на скриншот слишком длинная.',
            'screenshots.*.title.max' => 'Название скриншота не должно превышать 255 символов.',
            'screenshots.*.sort_order.integer' => 'Порядок сортировки должен быть числом.',
            'screenshots.*.sort_order.min' => 'Порядок сортировки не может быть отрицательным.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'название',
            'description' => 'описание',
            'occurred_at' => 'время проведения',
            'participants' => 'участники',
            'participants.*.character_id' => 'участник гильдии',
            'participants.*.external_name' => 'ник участника',
            'screenshots' => 'скриншоты',
            'screenshots.*.url' => 'ссылка на скриншот',
            'screenshots.*.title' => 'название скриншота',
            'screenshots.*.sort_order' => 'порядок сортировки',
        ];
    }
}

