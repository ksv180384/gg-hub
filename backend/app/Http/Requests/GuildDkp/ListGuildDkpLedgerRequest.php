<?php

namespace App\Http\Requests\GuildDkp;

use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGuildDkpLedgerRequest extends FormRequest
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
            'occurred_from' => ['nullable', 'date'],
            'occurred_to' => ['nullable', 'date', 'after_or_equal:occurred_from'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'event_history_title_id' => ['nullable', 'integer', Rule::exists('event_history_titles', 'id')],
            'source' => ['nullable', 'string', Rule::enum(GuildDkpLedgerSource::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'occurred_from.date' => 'Дата начала периода должна быть корректной.',
            'occurred_to.date' => 'Дата окончания периода должна быть корректной.',
            'occurred_to.after_or_equal' => 'Дата окончания периода не может быть раньше даты начала.',
            'user_name.max' => 'Ник пользователя не должен превышать 255 символов.',
            'event_history_title_id.integer' => 'Событие должно быть выбрано из списка.',
            'event_history_title_id.exists' => 'Выбранное событие не найдено.',
            'source.enum' => 'Выберите источник начисления из списка.',
        ];
    }
}
