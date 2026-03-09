<?php

namespace App\Http\Requests\Post;

use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Enums\PostVisibilityType;
use Domains\Post\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var Post|null $post */
        $post = $this->route('post');

        if (!$user || !$post) {
            return false;
        }

        // Пост может редактировать только его владелец
        return (int) $post->user_id === (int) $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'character_id' => ['nullable', 'integer', 'exists:characters,id'],
            'guild_id' => ['nullable', 'integer', 'exists:guilds,id'],
            'game_id' => ['nullable', 'integer', 'exists:games,id'],

            'is_visible_global' => ['required', 'boolean'],
            'is_visible_guild' => ['required', 'boolean'],

            'global_visibility_type' => [
                'nullable',
                'string',
                Rule::in(PostVisibilityType::values()),
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(PostStatus::values()),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            /** @var Post|null $post */
            $post = $this->route('post');

            if (!$user || !$post) {
                return;
            }

            $guildId = $this->input('guild_id') ?? $post->guild_id;
            if (!$guildId) {
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

            if ($isVisibleGuild && !$slugs->contains('redaktirovat-post')) {
                $validator->errors()->add('guild_id', 'У вас нет прав редактировать посты этой гильдии.');
            }

            if ($wantsGlobalAsGuild && !$slugs->contains('sozdavat-posty-ot-imeni-gildii')) {
                $validator->errors()->add('global_visibility_type', 'У вас нет прав создавать посты от имени гильдии.');
            }
        });
    }
}

