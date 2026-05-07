<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Enums\PostVisibilityType;
use Illuminate\Http\Request;

class BuildPostDataFromRequestAction
{
    /**
     * Собирает данные поста из HTTP‑запроса (создание/редактирование).
     *
     * На входе ожидаются поля:
     * - title, body, character_id, guild_id, game_id
     * - is_visible_global, is_visible_guild
     * - global_visibility_type: anonymous | guild | null
     * - status: published | draft | hidden
     *
     * На выходе — массив атрибутов модели Post (кроме user_id).
     *
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        $isVisibleGlobal = $request->boolean('is_visible_global');
        $isVisibleGuild = $request->boolean('is_visible_guild');

        $globalVisibilityType = $request->input('global_visibility_type'); // anonymous | guild | null
        $isAnonymous = $globalVisibilityType === PostVisibilityType::Anonymous->value;

        $statusGlobal = $request->input('status_global', PostStatus::Draft->value);
        $statusGuild = $request->input('status_guild', PostStatus::Draft->value);
        if (!\in_array($statusGlobal, PostStatus::values(), true)) {
            $statusGlobal = PostStatus::Draft->value;
        }
        if (!\in_array($statusGuild, PostStatus::values(), true)) {
            $statusGuild = PostStatus::Draft->value;
        }

        // Статусы по областям в зависимости от видимости:
        // - Если только «Для всех»      → guild = hidden
        // - Если только «Для гильдии»   → global = hidden
        // - Если обе галочки            → оба из запроса
        // - Если обе выключены          → оба hidden
        if (!$isVisibleGlobal) {
            $statusGlobal = PostStatus::Hidden->value;
        }
        if (!$isVisibleGuild) {
            $statusGuild = PostStatus::Hidden->value;
        }

        $body = $request->input('body') ?? '';
        $body = app(FixPostBodyHtmlAction::class)($body);
        $preview = app(BuildPostPreviewAction::class)($body);

        return [
            'character_id' => $request->input('character_id'),
            'guild_id' => $request->input('guild_id'),
            'game_id' => $request->input('game_id'),
            'title' => $request->input('title'),
            'preview' => $preview,
            'body' => $body,

            'is_visible_global' => $isVisibleGlobal,
            'is_visible_guild' => $isVisibleGuild,

            'is_anonymous' => $isAnonymous,
            'is_global_as_guild' => $isVisibleGlobal && $globalVisibilityType === PostVisibilityType::Guild->value,

            'status_global' => $statusGlobal,
            'status_guild' => $statusGuild,
        ];
    }
}

