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
            'dkp_base_points' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'distribute_dkp_to_participants' => ['sometimes', 'boolean'],

            'participants' => ['sometimes', 'nullable', 'array'],
            'participants.*.character_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild->id),
            ],
            'participants.*.external_name' => ['nullable', 'string', 'max:255'],
            'participants.*.dkp_coefficient' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'participants.*.dkp_points_override' => ['nullable', 'integer', 'min:0', 'max:1000000000'],

            'screenshots' => ['sometimes', 'nullable', 'array', 'max:5'],
            'screenshots.*.url' => ['nullable', 'required_without:screenshots.*.file', 'url', 'max:2000'],
            'screenshots.*.file' => ['nullable', 'required_without:screenshots.*.url', 'image', 'max:10240'],
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
            'dkp_base_points.integer' => 'Очки ДКП должны быть числом.',
            'dkp_base_points.min' => 'Очки ДКП не могут быть отрицательными.',

            'participants.array' => 'Список участников должен быть массивом.',
            'participants.*.character_id.integer' => 'Участник должен быть корректным персонажем.',
            'participants.*.character_id.exists' => 'Выбранный персонаж не состоит в этой гильдии.',
            'participants.*.external_name.max' => 'Ник участника не должен превышать 255 символов.',
            'participants.*.dkp_coefficient.numeric' => 'Коэффициент ДКП должен быть числом.',
            'participants.*.dkp_coefficient.min' => 'Коэффициент ДКП не может быть отрицательным.',
            'participants.*.dkp_points_override.integer' => 'Переопределение ДКП должно быть числом.',
            'participants.*.dkp_points_override.min' => 'Переопределение ДКП не может быть отрицательным.',

            'screenshots.array' => 'Список скриншотов должен быть массивом.',
            'screenshots.max' => 'Можно загрузить максимум 5 скриншотов.',
            'screenshots.*.url.required_without' => 'Добавьте файл скриншота или оставьте существующую ссылку.',
            'screenshots.*.url.url' => 'Ссылка на скриншот должна быть корректным URL.',
            'screenshots.*.url.max' => 'Ссылка на скриншот слишком длинная.',
            'screenshots.*.file.required_without' => 'Добавьте файл скриншота или оставьте существующую ссылку.',
            'screenshots.*.file.image' => 'Файл скриншота должен быть изображением.',
            'screenshots.*.file.max' => 'Файл скриншота не должен быть больше 10 МБ.',
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
            'dkp_base_points' => 'очки ДКП',
            'distribute_dkp_to_participants' => 'режим распределения ДКП',
            'participants' => 'участники',
            'participants.*.character_id' => 'участник гильдии',
            'participants.*.external_name' => 'ник участника',
            'participants.*.dkp_coefficient' => 'коэффициент ДКП',
            'participants.*.dkp_points_override' => 'переопределение ДКП',
            'screenshots' => 'скриншоты',
            'screenshots.*.url' => 'ссылка на скриншот',
            'screenshots.*.file' => 'файл скриншота',
            'screenshots.*.title' => 'название скриншота',
            'screenshots.*.sort_order' => 'порядок сортировки',
        ];
    }
}
