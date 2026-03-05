<?php

namespace Database\Seeders;

use App\Models\User;
use Domains\Access\Models\GuildRole;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Заполняет гильдию с id=1 персонажами.
 * Гильдия: game_id=4, localization_id=2, server_id=20.
 * Персонажи только у пользователей с одним персонажем. Пароль пользователей: password.
 * Добавляет 50 участников (создаёт пользователей и персонажей при нехватке).
 */
class GuildMembersSeeder extends Seeder
{
    private const GUILD_ID = 1;
    private const GAME_ID = 4;
    private const LOCALIZATION_ID = 2;
    private const SERVER_ID = 20;
    private const TARGET_MEMBERS_COUNT = 50;
    private const USER_PASSWORD = 'password';

    public function run(): void
    {
        $guild = Guild::query()->find(self::GUILD_ID);
        if (!$guild) {
            $this->command->error('Гильдия с id=' . self::GUILD_ID . ' не найдена. Создайте гильдию или измените GUILD_ID в сидере.');
            return;
        }

        $guild->update([
            'game_id' => self::GAME_ID,
            'localization_id' => self::LOCALIZATION_ID,
            'server_id' => self::SERVER_ID,
        ]);

        $noviceRole = GuildRole::query()
            ->where('guild_id', self::GUILD_ID)
            ->where('slug', 'novice')
            ->first();

        if (!$noviceRole) {
            $noviceRole = GuildRole::query()->create([
                'guild_id' => self::GUILD_ID,
                'name' => 'Новичок',
                'slug' => 'novice',
                'priority' => 0,
            ]);
        }

        $existingMemberCharacterIds = GuildMember::query()
            ->where('guild_id', self::GUILD_ID)
            ->pluck('character_id')
            ->all();

        $needed = self::TARGET_MEMBERS_COUNT - count($existingMemberCharacterIds);
        if ($needed <= 0) {
            $this->command->info('В гильдии уже достаточно участников.');
            return;
        }

        $userIdsWithOneCharacter = Character::query()
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) = 1')
            ->pluck('user_id');

        $candidateCharacterIds = Character::query()
            ->where('game_id', self::GAME_ID)
            ->where('localization_id', self::LOCALIZATION_ID)
            ->where('server_id', self::SERVER_ID)
            ->whereNotIn('id', $existingMemberCharacterIds)
            ->whereIn('user_id', $userIdsWithOneCharacter)
            ->limit($needed)
            ->pluck('id')
            ->all();

        $added = 0;
        foreach ($candidateCharacterIds as $characterId) {
            GuildMember::query()->create([
                'guild_id' => self::GUILD_ID,
                'character_id' => $characterId,
                'guild_role_id' => $noviceRole->id,
                'joined_at' => now(),
            ]);
            $existingMemberCharacterIds[] = $characterId;
            $added++;
        }

        $stillNeeded = self::TARGET_MEMBERS_COUNT - count($existingMemberCharacterIds);
        if ($stillNeeded > 0) {
            $baseEmail = 'guild1member_' . time() . '_';
            $baseName = 'Участник';
            for ($i = 0; $i < $stillNeeded; $i++) {
                $email = $baseEmail . $i . '@example.com';
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $baseName . ' ' . ($i + 1),
                        'password' => Hash::make(self::USER_PASSWORD),
                    ]
                );

                if ($user->wasRecentlyCreated) {
                    $character = Character::query()->create([
                        'user_id' => $user->id,
                        'game_id' => self::GAME_ID,
                        'localization_id' => self::LOCALIZATION_ID,
                        'server_id' => self::SERVER_ID,
                        'name' => $baseName . ' ' . ($i + 1) . ' (' . $email . ')',
                        'is_main' => true,
                    ]);
                } else {
                    $character = Character::query()
                        ->where('user_id', $user->id)
                        ->where('game_id', self::GAME_ID)
                        ->first();
                }

                if (!$character) {
                    $character = Character::query()->create([
                        'user_id' => $user->id,
                        'game_id' => self::GAME_ID,
                        'localization_id' => self::LOCALIZATION_ID,
                        'server_id' => self::SERVER_ID,
                        'name' => $baseName . ' ' . ($i + 1) . ' (' . $email . ')',
                        'is_main' => true,
                    ]);
                }

                if (!GuildMember::query()->where('guild_id', self::GUILD_ID)->where('character_id', $character->id)->exists()) {
                    GuildMember::query()->create([
                        'guild_id' => self::GUILD_ID,
                        'character_id' => $character->id,
                        'guild_role_id' => $noviceRole->id,
                        'joined_at' => now(),
                    ]);
                    $added++;
                }
            }
        }

        $this->command->info(
            'Добавлено участников в гильдию: ' . $added . '. Всего в гильдии: '
            . GuildMember::query()->where('guild_id', self::GUILD_ID)->count() . '.'
        );
    }
}
