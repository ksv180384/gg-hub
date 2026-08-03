<?php

declare(strict_types=1);

namespace App\Actions\GuildActivity;

use App\Filters\GuildActivityLogFilter;
use App\GuildActivityLog;
use Domains\Guild\Models\Guild;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListGuildActivityLogsAction
{
    /** @return LengthAwarePaginator<int, GuildActivityLog> */
    public function __invoke(Guild $guild, GuildActivityLogFilter $filter): LengthAwarePaginator
    {
        return GuildActivityLog::query()
            ->select([
                'id',
                'guild_id',
                'actor_user_id',
                'actor_name',
                'category',
                'action',
                'subject_type',
                'subject_id',
                'subject_name',
                'description',
                'old_values',
                'new_values',
                'metadata',
                'created_at',
            ])
            ->whereBelongsTo($guild)
            ->with('actor:id,name')
            ->filter($filter)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();
    }
}
