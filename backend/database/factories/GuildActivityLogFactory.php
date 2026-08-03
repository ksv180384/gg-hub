<?php

declare(strict_types=1);

namespace Database\Factories;

use App\GuildActivityLog;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuildActivityLog> */
class GuildActivityLogFactory extends Factory
{
    protected $model = GuildActivityLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'guild_id' => Guild::query()->value('id'),
            'actor_user_id' => null,
            'actor_name' => null,
            'category' => GuildActivityLog::CATEGORY_GUILD,
            'action' => 'guild.updated',
            'subject_type' => null,
            'subject_id' => null,
            'subject_name' => null,
            'description' => fake()->sentence(),
            'old_values' => null,
            'new_values' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    public function forGuild(Guild $guild): static
    {
        return $this->state(fn (): array => ['guild_id' => $guild->getKey()]);
    }
}
