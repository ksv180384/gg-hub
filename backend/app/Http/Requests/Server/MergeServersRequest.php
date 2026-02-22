<?php

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

class MergeServersRequest extends FormRequest
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
        return [
            'target_server_id' => ['required', 'integer', 'exists:servers,id'],
            'source_server_ids' => ['required', 'array', 'min:1'],
            'source_server_ids.*' => ['integer', 'exists:servers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target_server_id' => 'целевой сервер',
            'source_server_ids' => 'объединяемые сервера',
        ];
    }
}
