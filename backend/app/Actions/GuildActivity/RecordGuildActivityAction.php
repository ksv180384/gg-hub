<?php

declare(strict_types=1);

namespace App\Actions\GuildActivity;

use App\GuildActivityLog;
use Domains\Guild\Models\Guild;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordGuildActivityAction
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function __invoke(
        Guild|int $guild,
        ?User $actor,
        string $category,
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $subjectName = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): GuildActivityLog {
        return GuildActivityLog::query()->create([
            'guild_id' => $guild instanceof Guild ? $guild->getKey() : $guild,
            'actor_user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'category' => $category,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'subject_name' => $subjectName,
            'description' => $description,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'metadata' => $metadata ?: null,
        ]);
    }
}
