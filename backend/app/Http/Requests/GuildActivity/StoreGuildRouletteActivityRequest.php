<?php

declare(strict_types=1);

namespace App\Http\Requests\GuildActivity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuildRouletteActivityRequest extends FormRequest
{
    public const ACTIONS = [
        'roulette.entries_updated',
        'roulette.entry_added',
        'roulette.entry_removed',
        'roulette.enrollment_opened',
        'roulette.enrollment_closed',
        'roulette.elimination_mode_enabled',
        'roulette.elimination_mode_disabled',
        'roulette.dkp_coefficients_enabled',
        'roulette.dkp_coefficients_disabled',
        'roulette.dkp_coefficients_updated',
        'roulette.external_dkp_coefficients_updated',
        'roulette.spin_started',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guild_id' => ['required', 'integer', 'exists:guilds,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
