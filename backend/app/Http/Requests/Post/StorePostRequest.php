<?php

namespace App\Http\Requests\Post;

use Domains\Guild\Models\Guild;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Enums\PostVisibilityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        if (\is_string($title)) {
            $this->merge(['title' => trim($title)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'character_id' => ['required', 'integer', 'exists:characters,id'],
            'guild_id' => ['nullable', 'integer', 'exists:guilds,id'],
            'game_id' => ['nullable', 'integer', 'exists:games,id'],

            'is_visible_global' => ['required', 'boolean'],
            'is_visible_guild' => ['required', 'boolean'],

            'global_visibility_type' => [
                'nullable',
                'string',
                Rule::in(PostVisibilityType::values()),
            ],

            // Статус blocked выставляется только администратором
            'status_global' => [
                'nullable',
                'string',
                Rule::in(['pending', 'published', 'draft', 'hidden', 'rejected']),
            ],
            'status_guild' => [
                'nullable',
                'string',
                Rule::in(['pending', 'published', 'draft', 'hidden', 'rejected']),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $guildId = $this->input('guild_id');

            if (!$user || !$guildId) {
                return;
            }

            $guild = Guild::query()->find($guildId);
            if (!$guild) {
                return;
            }

            $slugs = app(GetUserGuildPermissionSlugsAction::class)($user, $guild);

            $isVisibleGuild = (bool) $this->boolean('is_visible_guild');
            $isVisibleGlobal = (bool) $this->boolean('is_visible_global');
            $globalVisibilityType = $this->input('global_visibility_type');
            $wantsGlobalAsGuild = $isVisibleGlobal && $globalVisibilityType === PostVisibilityType::Guild->value;

            if ($isVisibleGuild && !$slugs->contains('dobavliat-post')) {
                $validator->errors()->add('guild_id', 'У вас нет прав добавлять посты в раздел гильдии.');
            }

            if ($wantsGlobalAsGuild && !$slugs->contains('sozdavat-posty-ot-imeni-gildii')) {
                $validator->errors()->add('global_visibility_type', 'У вас нет прав создавать посты от имени гильдии.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'заголовок',
            'body' => 'текст поста',
            'character_id' => 'персонаж',
            'guild_id' => 'гильдия',
            'game_id' => 'игра',
            'is_visible_global' => 'публикация для всех',
            'is_visible_guild' => 'публикация для гильдии',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Введите заголовок поста.',
            'character_id.required' => 'Выберите персонажа.',
        ];
    }
}

