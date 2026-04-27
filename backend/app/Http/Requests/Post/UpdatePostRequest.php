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
        if ((int) $post->user_id !== (int) $user->id) {
            return false;
        }

        // Редактирование недоступно только если заблокированы и общий, и гильдейский просмотр
        if ($post->status_global === PostStatus::Blocked->value && $post->status_guild === PostStatus::Blocked->value) {
            return false;
        }

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

            // Статус blocked выставляется только администратором, автор не может его передать
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

            // Право на редактирование постов гильдии требуется только при публикации в гильдию (не при сохранении заблокированного/скрытого поста).
            $statusGuild = $this->input('status_guild', $post->status_guild);
            $wantsGuildVisible = $isVisibleGuild && $statusGuild !== PostStatus::Hidden->value;
            if ($wantsGuildVisible && !$slugs->contains('redaktirovat-post')) {
                $validator->errors()->add('guild_id', 'У вас нет прав редактировать посты этой гильдии.');
            }

            // Право «от имени гильдии» требуется только при публикации в общий журнал (не при сохранении заблокированного поста).
            $statusGlobal = $this->input('status_global', $post->status_global);
            $wantsGlobalVisibleAsGuild = $wantsGlobalAsGuild && $statusGlobal !== PostStatus::Hidden->value;
            if ($wantsGlobalVisibleAsGuild && !$slugs->contains('sozdavat-posty-ot-imeni-gildii')) {
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

