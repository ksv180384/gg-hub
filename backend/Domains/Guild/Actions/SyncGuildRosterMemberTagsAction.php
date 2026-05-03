<?php

namespace Domains\Guild\Actions;

use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\Tag\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Синхронизирует теги участника в контексте гильдии (таблица character_guild_tag).
 * Требуется право izmeniat-tegi-polzovatelei-gildii.
 */
final class SyncGuildRosterMemberTagsAction
{
    /**
     * @param  array<int, int>  $tagIds
     */
    public function __invoke(Guild $guild, int $characterId, array $tagIds): void
    {
        $member = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'character_id' => ['Участник не найден в гильдии.'],
            ]);
        }

        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        if ($tagIds !== []) {
            $count = Tag::query()->whereIn('id', $tagIds)->count();
            if ($count !== count($tagIds)) {
                throw ValidationException::withMessages([
                    'tag_ids' => ['Один или несколько тегов не найдены.'],
                ]);
            }
        }

        DB::table('character_guild_tag')
            ->where('guild_id', $guild->id)
            ->where('character_id', $characterId)
            ->delete();

        if ($tagIds !== []) {
            $rows = array_map(
                fn (int $tagId) => [
                    'guild_id' => $guild->id,
                    'character_id' => $characterId,
                    'tag_id' => $tagId,
                ],
                $tagIds
            );
            DB::table('character_guild_tag')->insert($rows);
        }
    }
}
