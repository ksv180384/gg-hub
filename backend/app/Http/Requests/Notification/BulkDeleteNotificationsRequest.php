<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteNotificationsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Укажите хотя бы одно оповещение для удаления.',
            'ids.array' => 'Список оповещений должен быть массивом идентификаторов.',
            'ids.min' => 'Укажите хотя бы одно оповещение для удаления.',
            'ids.max' => 'Нельзя удалить больше 200 оповещений за один раз.',
            'ids.*.required' => 'Идентификатор оповещения обязателен.',
            'ids.*.integer' => 'Идентификатор оповещения должен быть числом.',
            'ids.*.min' => 'Идентификатор оповещения должен быть положительным числом.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'список идентификаторов',
            'ids.*' => 'идентификатор оповещения',
        ];
    }

    /**
     * @return int[]
     */
    public function notificationIds(): array
    {
        $raw = $this->input('ids', []);
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $raw),
            static fn (int $id): bool => $id > 0
        )));
    }
}
