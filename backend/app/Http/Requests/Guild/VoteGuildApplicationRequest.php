<?php

namespace App\Http\Requests\Guild;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoteGuildApplicationRequest extends FormRequest
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
            'vote' => ['required', 'string', Rule::in(['like', 'dislike'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vote.required' => 'Укажите тип голоса.',
            'vote.in' => 'Допустимы только like или dislike.',
        ];
    }
}
