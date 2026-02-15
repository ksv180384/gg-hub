<?php

namespace App\Http\Requests\Localization;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocalizationRequest extends FormRequest
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
        $gameId = $this->route('game')?->getKey();

        return [
            'code' => [
                'required',
                'string',
                'max:16',
                \Illuminate\Validation\Rule::unique('localizations', 'code')->where('game_id', $gameId),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
