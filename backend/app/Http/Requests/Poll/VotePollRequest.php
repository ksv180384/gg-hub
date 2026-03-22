<?php

namespace App\Http\Requests\Poll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VotePollRequest extends FormRequest
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
        $pollId = $this->route('poll');
        $poll = $guild && $pollId
            ? \Domains\Poll\Models\Poll::where('guild_id', $guild->id)->with('options')->find($pollId)
            : null;
        $optionIds = $poll?->options->pluck('id')->toArray() ?? [];

        return [
            'option_id' => ['required', 'integer', Rule::in($optionIds)],
            'character_id' => [
                'required',
                'integer',
                Rule::exists('guild_members', 'character_id')->where('guild_id', $guild?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'option_id.required' => 'Выберите вариант ответа.',
            'option_id.in' => 'Выбран неверный вариант.',
            'character_id.required' => 'Укажите персонажа для голосования.',
            'character_id.exists' => 'Персонаж не состоит в этой гильдии.',
        ];
    }
}
