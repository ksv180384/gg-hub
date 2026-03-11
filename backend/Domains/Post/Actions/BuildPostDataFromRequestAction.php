<?php

namespace Domains\Post\Actions;

use Domains\Post\Enums\PostStatus;
use Domains\Post\Enums\PostVisibilityType;
use Illuminate\Http\Request;
use Stevebauman\Purify\Facades\Purify;

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

        $status = $request->input('status', PostStatus::Draft->value);
        if (!\in_array($status, PostStatus::values(), true)) {
            $status = PostStatus::Draft->value;
        }

        // Распределение одного выбранного статуса по областям
        // - Если включены только «Для всех»      → global = выбранный, guild = hidden
        // - Если включены только «Для гильдии»   → global = hidden,    guild = выбранный
        // - Если включены обе галочки            → global = выбранный, guild = выбранный
        // - Если обе галочки выключены           → global = hidden,    guild = hidden
        $statusGlobal = null;
        $statusGuild = null;

        if ($isVisibleGlobal && !$isVisibleGuild) {
            $statusGlobal = $status;
            $statusGuild = 'hidden';
        } elseif (!$isVisibleGlobal && $isVisibleGuild) {
            $statusGlobal = 'hidden';
            $statusGuild = $status;
        } elseif ($isVisibleGlobal && $isVisibleGuild) {
            $statusGlobal = $status;
            $statusGuild = $status;
        } else {
            $statusGlobal = 'hidden';
            $statusGuild = 'hidden';
        }

        $body = $request->input('body') ?? '';
        $body = app(FixPostBodyHtmlAction::class)($body);
        $body = Purify::config('guild_rich_text')->clean($body);
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

