<?php

declare(strict_types=1);

namespace App\Http\Requests\GuildActivity;

use App\GuildActivityLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListGuildActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'category' => ['nullable', 'string', Rule::in(GuildActivityLog::CATEGORIES)],
            'action' => ['nullable', 'string', 'max:80'],
            'actor_name' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
