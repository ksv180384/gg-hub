<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('game_id')
                ->nullable()
                ->after('user_id')
                ->constrained('games')
                ->nullOnDelete();
            $table->foreignId('guild_id')
                ->nullable()
                ->after('game_id')
                ->constrained('guilds')
                ->nullOnDelete();
        });

        DB::table('notifications')
            ->select(['id', 'link', 'message'])
            ->orderBy('id')
            ->chunkById(200, function ($notifications): void {
                foreach ($notifications as $notification) {
                    $guildId = $this->resolveGuildId((string) $notification->link);
                    if ($guildId === null) {
                        continue;
                    }

                    $guild = DB::table('guilds')
                        ->join('games', 'games.id', '=', 'guilds.game_id')
                        ->where('guilds.id', $guildId)
                        ->select([
                            'guilds.id as guild_id',
                            'guilds.name as guild_name',
                            'games.id as game_id',
                            'games.name as game_name',
                        ])
                        ->first();
                    if (! $guild) {
                        continue;
                    }

                    $message = (string) $notification->message;
                    $legacyPrefix = "Игра «{$guild->game_name}», гильдия «{$guild->guild_name}»: ";
                    if (str_starts_with($message, $legacyPrefix)) {
                        $message = substr($message, strlen($legacyPrefix));
                    }

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update([
                            'game_id' => $guild->game_id,
                            'guild_id' => $guild->guild_id,
                            'message' => $message,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guild_id');
            $table->dropConstrainedForeignId('game_id');
        });
    }

    private function resolveGuildId(string $link): ?int
    {
        if (preg_match('~(?:^|/)guilds/(\d+)(?:/|$)~', $link, $matches)) {
            return (int) $matches[1];
        }

        if (! preg_match('~(?:^|/)my-posts/(\d+)(?:/|$)~', $link, $matches)) {
            return null;
        }

        $guildId = DB::table('posts')
            ->where('id', (int) $matches[1])
            ->value('guild_id');

        return $guildId ? (int) $guildId : null;
    }
};
