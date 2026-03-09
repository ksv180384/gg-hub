<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;

/**
 * Применяет правила модерации к данным поста при сохранении как «опубликован»:
 * - Публикация «для всех» всегда уходит на модерацию (status_global → pending).
 * - Публикация «для гильдии»: если у пользователя нет права publikovat-post в гильдии,
 *   статус в гильдии переводится в pending и возвращается guild_id для отправки уведомлений модераторам.
 *
 * @return array{data: array<string, mixed>, notify_guild_id: int|null}
 */
class ApplyPostModerationRulesAction
{
    private const PERMISSION_PUBLISH_POST = 'publikovat-post';

    public function __construct(
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction
    ) {}

    /**
     * @param  array<string, mixed>  $data  Данные поста (в т.ч. status_global, status_guild, guild_id)
     * @return array{data: array<string, mixed>, notify_guild_id: int|null}
     */
    public function __invoke(array $data, User $user): array
    {
        $notifyGuildId = null;

        // Публикация «для всех» всегда проходит модерацию — не показываем всем до одобрения
        if (isset($data['status_global']) && $data['status_global'] === PostStatus::Published->value) {
            $data['status_global'] = PostStatus::Pending->value;
            $data['published_at_global'] = null;
        }

        // Публикация «для гильдии»: при отсутствии права publikovat-post — на модерацию и уведомление
        $guildId = $data['guild_id'] ?? null;
        if (
            $guildId
            && isset($data['status_guild'])
            && $data['status_guild'] === PostStatus::Published->value
        ) {
            $guild = Guild::query()->find($guildId);
            if ($guild) {
                $userSlugs = ($this->getUserGuildPermissionSlugsAction)($user, $guild);
                if (!$userSlugs->contains(self::PERMISSION_PUBLISH_POST)) {
                    $data['status_guild'] = PostStatus::Pending->value;
                    $data['published_at_guild'] = null;
                    $notifyGuildId = (int) $guild->id;
                }
            }
        }

        return [
            'data' => $data,
            'notify_guild_id' => $notifyGuildId,
        ];
    }
}
