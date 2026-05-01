<?php

namespace Domains\Guild\Actions;

use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Http\Requests\Guild\UpdateGuildRequest;
use App\Services\GuildLogoService;
use Domains\Character\Models\Character;
use Domains\Game\Models\Server;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\Tag\Models\Tag;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Stevebauman\Purify\Facades\Purify;

class UpdateGuildAction
{
    /** Соответствие поле гильдии → slug права, необходимого для его изменения. */
    private const FIELD_PERMISSION_MAP = [
        'name' => 'redaktirovanie-dannyx-gildii',
        'localization_id' => 'redaktirovanie-dannyx-gildii',
        'server_id' => 'redaktirovanie-dannyx-gildii',
        'show_roster_to_all' => 'redaktirovanie-dannyx-gildii',
        'tag_ids' => 'izmeniat-tegi-gildii',
        'about_text' => 'redaktirovanie-opisanie-gildii',
        'charter_text' => 'redaktirovanie-ustav-gildii',
        'is_recruiting' => 'redaktirovat-formu-zaiavki-v-giliudiiu',
        'discord_webhook_url' => 'redaktirovanie-dannyx-gildii',
        'discord_notify_application_new' => 'redaktirovanie-dannyx-gildii',
        'discord_notify_member_joined' => 'redaktirovanie-dannyx-gildii',
        'discord_notify_member_left' => 'redaktirovanie-dannyx-gildii',
        'discord_notify_event_starting' => 'redaktirovanie-dannyx-gildii',
        'discord_notify_poll_started' => 'redaktirovanie-dannyx-gildii',
        'discord_notify_role_changed' => 'redaktirovanie-dannyx-gildii',
        'discord_notify_post_published' => 'redaktirovanie-dannyx-gildii',
    ];

    /** Человекочитаемые имена полей гильдии для сообщений об ошибках. */
    private const FIELD_LABELS = [
        'name' => 'название',
        'localization_id' => 'локализацию',
        'server_id' => 'сервер',
        'show_roster_to_all' => 'видимость состава',
        'tag_ids' => 'теги',
        'about_text' => 'описание гильдии',
        'charter_text' => 'устав гильдии',
        'is_recruiting' => 'статус набора в гильдию',
        'discord_webhook_url' => 'URL Discord-вебхука',
        'discord_notify_application_new' => 'оповещение «Новая заявка вступления в гильдию»',
        'discord_notify_member_joined' => 'оповещение «Пользователь вступил в гильдию»',
        'discord_notify_member_left' => 'оповещение «Пользователь покинул гильдию»',
        'discord_notify_event_starting' => 'оповещение «Начало гильдейского события»',
        'discord_notify_poll_started' => 'оповещение «Запуск нового голосования»',
        'discord_notify_role_changed' => 'оповещение «Смена роли пользователю»',
        'discord_notify_post_published' => 'оповещение «Публикация нового поста гильдии»',
    ];

    public function __construct(
        private GuildRepositoryInterface $guildRepository,
        private GuildLogoService $guildLogoService,
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction
    ) {}

    public function __invoke(Guild $guild, UpdateGuildRequest $request): Guild
    {
        $data = $request->validated();
        $user = $request->user();

        // Права определяем строго по действующим ролям в гильдии. Текущий лидер
        // (владелец персонажа leader_character_id) получает все slug'и гильдии
        // внутри GetUserGuildPermissionSlugsAction — отдельный байпас по owner_id
        // не используется, иначе бывший лидер-создатель продолжит редактировать
        // гильдию после смены лидера.
        $userSlugs = $user
            ? ($this->getUserGuildPermissionSlugsAction)($user, $guild)->all()
            : [];

        foreach (self::FIELD_PERMISSION_MAP as $field => $requiredSlug) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (!in_array($requiredSlug, $userSlugs, true)) {
                $label = self::FIELD_LABELS[$field] ?? $field;
                $this->denyWithMessage('Недостаточно прав, чтобы изменить ' . $label . '.');
            }
        }

        // Смена лидера: только текущий лидер гильдии (владелец leader_character_id).
        if (array_key_exists('leader_character_id', $data)) {
            $leaderByCharacter = false;
            if ($user && $guild->leader_character_id) {
                $leaderCharacter = Character::query()->find($guild->leader_character_id);
                $leaderByCharacter = $leaderCharacter
                    && (int) $leaderCharacter->user_id === (int) $user->id;
            }
            if (!$leaderByCharacter) {
                $this->denyWithMessage(
                    'Сменить лидера гильдии может только текущий лидер.'
                );
            }
        }

        // Логотип и remove_logo — часть «редактирования данных гильдии».
        $canEditGuildData = in_array('redaktirovanie-dannyx-gildii', $userSlugs, true);
        $hasLogoFile = $request->hasFile('logo');
        $removeLogoRequested = $request->boolean('remove_logo');
        if (($hasLogoFile || $removeLogoRequested) && !$canEditGuildData) {
            $this->denyWithMessage('Недостаточно прав, чтобы изменить логотип гильдии.');
        }

        if (array_key_exists('about_text', $data) && $data['about_text'] !== null) {
            $data['about_text'] = Purify::config('guild_rich_text')->clean($data['about_text']);
        }
        if (array_key_exists('charter_text', $data) && $data['charter_text'] !== null) {
            $data['charter_text'] = Purify::config('guild_rich_text')->clean($data['charter_text']);
        }

        if ($removeLogoRequested) {
            $this->guildLogoService->delete($guild);
            $data['logo_path'] = null;
        }

        if ($hasLogoFile) {
            /** @var UploadedFile $file */
            $file = $request->file('logo');
            $data['logo_path'] = $this->guildLogoService->store($file, $guild);
        }

        if (isset($data['server_id'])) {
            $server = Server::query()->findOrFail((int) $data['server_id']);
            $data['game_id'] = $server->game_id;
            $data['localization_id'] = $server->localization_id;
        }

        unset($data['logo'], $data['remove_logo']);
        if (array_key_exists('tag_ids', $data)) {
            $tagIds = is_array($data['tag_ids']) ? array_map('intval', $data['tag_ids']) : [];
            $tagIds = array_values(array_unique(array_filter($tagIds)));
            // К гильдии можно привязывать только общие теги (обе ссылки NULL)
            // и теги этой же гильдии. Всё остальное (личные теги пользователей,
            // теги чужих гильдий) отсекается на всякий случай и здесь — например,
            // чтобы «легаси»-привязки, оставшиеся в `selectedTagIds` на фронте,
            // при следующем сохранении автоматически отцеплялись от гильдии.
            $allowedIds = $tagIds === []
                ? []
                : Tag::query()
                    ->whereIn('id', $tagIds)
                    ->whereNull('used_by_user_id')
                    ->where(function ($q) use ($guild) {
                        $q->whereNull('used_by_guild_id')
                            ->orWhere('used_by_guild_id', $guild->id);
                    })
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            // Сохраняем скрытые привязки, которых фронт не видит (пикер и отображение
            // отдают только `is_hidden = false`). Иначе, если админ временно скрыл тег,
            // он отцепится от гильдии при ближайшем сохранении настроек.
            $hiddenAttachedIds = $guild->tags()
                ->where('is_hidden', true)
                ->pluck('tags.id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $finalIds = array_values(array_unique(array_merge($allowedIds, $hiddenAttachedIds)));
            $guild->tags()->sync($finalIds);
            unset($data['tag_ids']);
        }
        $logoWasReplaced = $request->hasFile('logo');
        // Смена сервера/локализации возможна только при единственном участнике-лидере
        // (проверено в UpdateGuildRequest) — синхронизируем и этого персонажа.
        $syncLeaderCharacter = isset($data['server_id']);

        // Смена лидера: прошлому лидеру — роль «Новичок» (slug: novice), новому — «Лидер» (slug: leader).
        $previousLeaderCharacterId = (int) ($guild->leader_character_id ?? 0);
        $newLeaderCharacterId = array_key_exists('leader_character_id', $data)
            ? (int) $data['leader_character_id']
            : $previousLeaderCharacterId;
        $leaderChanged = array_key_exists('leader_character_id', $data)
            && $newLeaderCharacterId !== 0
            && $newLeaderCharacterId !== $previousLeaderCharacterId;

        $guild = DB::transaction(function () use (
            $guild,
            $data,
            $syncLeaderCharacter,
            $leaderChanged,
            $previousLeaderCharacterId,
            $newLeaderCharacterId
        ) {
            $guild = $this->guildRepository->update($guild, $data);
            if ($syncLeaderCharacter && $guild->leader_character_id) {
                Character::query()
                    ->where('id', $guild->leader_character_id)
                    ->update([
                        'game_id' => $guild->game_id,
                        'localization_id' => $guild->localization_id,
                        'server_id' => $guild->server_id,
                    ]);
            }
            if ($leaderChanged) {
                $this->reassignLeaderRoles($guild, $previousLeaderCharacterId, $newLeaderCharacterId);
            }

            return $guild;
        });
        if ($logoWasReplaced) {
            $guild->touch();
        }
        $guild->loadCount('members')->load([
            'game',
            'localization',
            'server',
            'leader',
            'tags' => fn ($q) => $q->notHidden(),
        ]);
        return $guild;
    }

    /**
     * Бросает 403 с человекочитаемым сообщением в JSON-формате `{ message }`.
     */
    private function denyWithMessage(string $message): void
    {
        throw new HttpResponseException(
            response()->json(['message' => $message], 403)
        );
    }

    /**
     * При смене лидера: новый получает роль `leader`, прошлый — `novice`
     * (или ближайший fallback: любая роль гильдии, кроме `leader`, с наименьшим приоритетом).
     */
    private function reassignLeaderRoles(
        Guild $guild,
        int $previousLeaderCharacterId,
        int $newLeaderCharacterId
    ): void {
        $leaderRoleId = $guild->roles()->where('slug', 'leader')->value('id');
        $noviceRoleId = $guild->roles()->where('slug', 'novice')->value('id')
            ?? $guild->roles()->where('slug', '!=', 'leader')->orderBy('priority')->value('id');

        if ($leaderRoleId) {
            GuildMember::query()
                ->where('guild_id', $guild->id)
                ->where('character_id', $newLeaderCharacterId)
                ->update(['guild_role_id' => $leaderRoleId]);
        }
        if ($noviceRoleId && $previousLeaderCharacterId !== 0) {
            GuildMember::query()
                ->where('guild_id', $guild->id)
                ->where('character_id', $previousLeaderCharacterId)
                ->update(['guild_role_id' => $noviceRoleId]);
        }
    }
}
