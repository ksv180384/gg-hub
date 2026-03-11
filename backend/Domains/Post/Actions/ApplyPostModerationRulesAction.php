<?php

namespace Domains\Post\Actions;

use App\Models\User;
use Carbon\Carbon;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;

/**
 * Применяет правила модерации к данным поста при сохранении как «опубликован»:
 * - Публикация «для всех» всегда уходит на модерацию (status_global → pending).
 * - Публикация «для гильдии»: при наличии права publikovat-post — сразу публикуем (published_at_guild = now);
 *   при отсутствии права — статус в pending и guild_id для уведомления модераторов.
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

        // Публикация «для гильдии»
        $guildId = $data['guild_id'] ?? null;
        if (isset($data['status_guild'])) {
            if ($data['status_guild'] !== PostStatus::Published->value) {
                $data['published_at_guild'] = null;
            } elseif ($guildId) {
                $guild = Guild::query()->find($guildId);
                if ($guild) {
                    $userSlugs = ($this->getUserGuildPermissionSlugsAction)($user, $guild);
                    if ($userSlugs->contains(self::PERMISSION_PUBLISH_POST)) {
                        $data['published_at_guild'] = Carbon::now();
                    } else {
                        $data['status_guild'] = PostStatus::Pending->value;
                        $data['published_at_guild'] = null;
                        $notifyGuildId = (int) $guild->id;
                    }
                }
            }
        }

        return [
            'data' => $data,
            'notify_guild_id' => $notifyGuildId,
        ];
    }
}
