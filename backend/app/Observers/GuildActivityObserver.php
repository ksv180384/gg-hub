<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\GuildActivity\RecordGuildActivityAction;
use App\GuildActivityLog;
use Domains\Access\Models\GuildRole;
use Domains\Character\Models\Character;
use Domains\Event\Models\Event;
use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventParticipant;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildAuction\Models\GuildAuctionBid;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildBank\Models\GuildBankItemTier;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class GuildActivityObserver
{
    public function __construct(
        private RecordGuildActivityAction $recordGuildActivityAction,
    ) {}

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if ($changes === [] || $model instanceof Guild) {
            return;
        }

        if ($model instanceof GuildAuctionLot && ! array_key_exists('status', $changes)) {
            return;
        }

        $this->record($model, 'updated', $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    /** @param array<string, mixed> $changes */
    private function record(Model $model, string $event, array $changes = []): void
    {
        $context = $this->context($model, $event, $changes);

        if ($context === null) {
            return;
        }

        $actor = Auth::user();
        $actor = $actor instanceof User ? $actor : null;

        ($this->recordGuildActivityAction)(
            $context['guild_id'],
            $actor,
            $context['category'],
            $context['action'],
            $context['description'],
            $model,
            $context['subject_name'],
            $event === 'updated' ? $this->safeValues(Arr::only($model->getRawOriginal(), array_keys($changes))) : [],
            $event === 'updated' ? $this->safeValues($changes) : [],
            $context['metadata'],
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{guild_id:int,category:string,action:string,description:string,subject_name:?string,metadata:array<string,mixed>}|null
     */
    private function context(Model $model, string $event, array $changes): ?array
    {
        $guildId = $this->guildId($model);

        if ($guildId === null) {
            return null;
        }

        $subjectName = $this->subjectName($model);
        $category = $this->category($model);
        $action = $this->action($model, $event, $changes);
        $description = $this->description($model, $event, $subjectName, $changes);

        return [
            'guild_id' => $guildId,
            'category' => $category,
            'action' => $action,
            'description' => $description,
            'subject_name' => $subjectName,
            'metadata' => $changes === [] ? [] : ['changed_fields' => array_keys($changes)],
        ];
    }

    private function guildId(Model $model): ?int
    {
        if ($model instanceof EventParticipant) {
            $guildId = Event::query()->whereKey($model->event_id)->value('guild_id');

            return $guildId ? (int) $guildId : null;
        }

        if ($model instanceof PostComment) {
            $guildId = Post::query()->whereKey($model->post_id)->value('guild_id');

            return $guildId ? (int) $guildId : null;
        }

        $guildId = $model instanceof Guild
            ? $model->getKey()
            : ($model->getAttribute('guild_id') ?: $model->getRawOriginal('guild_id'));

        return $guildId ? (int) $guildId : null;
    }

    private function category(Model $model): string
    {
        return match (true) {
            $model instanceof GuildAuctionLot,
            $model instanceof GuildAuctionBid => GuildActivityLog::CATEGORY_AUCTION,
            $model instanceof GuildBankItem,
            $model instanceof GuildBankItemGrant,
            $model instanceof GuildBankItemTier => GuildActivityLog::CATEGORY_STORAGE,
            $model instanceof GuildMember => GuildActivityLog::CATEGORY_MEMBERS,
            $model instanceof GuildRole => GuildActivityLog::CATEGORY_ACCESS,
            $model instanceof Guild => GuildActivityLog::CATEGORY_GUILD,
            $model instanceof Post,
            $model instanceof PostComment => GuildActivityLog::CATEGORY_JOURNAL,
            default => GuildActivityLog::CATEGORY_EVENTS,
        };
    }

    /** @param array<string, mixed> $changes */
    private function action(Model $model, string $event, array $changes): string
    {
        if ($model instanceof GuildAuctionLot && $event === 'updated') {
            return 'auction.lot_closed';
        }

        if ($model instanceof GuildAuctionBid) {
            return 'auction.bid_placed';
        }

        if ($model instanceof GuildMember) {
            if ($event === 'created') {
                return 'member.joined';
            }

            if ($event === 'deleted') {
                return $this->memberDepartureAction($model);
            }

            return array_key_exists('guild_role_id', $changes)
                ? 'member.role_changed'
                : 'member.updated';
        }

        if ($model instanceof EventParticipant) {
            return $event === 'created' ? 'calendar.declined' : 'calendar.decline_removed';
        }

        $prefix = match (true) {
            $model instanceof GuildAuctionLot => 'auction.lot',
            $model instanceof GuildBankItem => 'storage.item',
            $model instanceof GuildBankItemGrant => 'storage.grant',
            $model instanceof GuildBankItemTier => 'storage.tier',
            $model instanceof GuildRole => 'access.role',
            $model instanceof Guild => 'guild',
            $model instanceof Post => 'journal.post',
            $model instanceof PostComment => 'journal.comment',
            $model instanceof Event => 'calendar.event',
            $model instanceof EventHistory => 'events.history',
            default => 'guild.activity',
        };

        return $prefix.'.'.$event;
    }

    private function subjectName(Model $model): ?string
    {
        if ($model instanceof GuildMember) {
            return Character::query()->whereKey($model->character_id)->value('name')
                ?? 'Персонаж #'.$model->character_id;
        }

        if ($model instanceof GuildAuctionLot) {
            return GuildBankItem::query()->whereKey($model->guild_bank_item_id)->value('name')
                ?? 'Лот #'.$model->getKey();
        }

        if ($model instanceof GuildAuctionBid) {
            return 'Ставка #'.$model->getKey();
        }

        if ($model instanceof GuildBankItemGrant) {
            return GuildBankItem::query()->whereKey($model->guild_bank_item_id)->value('name')
                ?? 'Выдача #'.$model->getKey();
        }

        if ($model instanceof EventParticipant) {
            return Event::query()->whereKey($model->event_id)->value('title')
                ?? 'Событие #'.$model->event_id;
        }

        if ($model instanceof PostComment) {
            return Post::query()->whereKey($model->post_id)->value('title')
                ?? 'Комментарий #'.$model->getKey();
        }

        foreach (['name', 'title'] as $attribute) {
            $value = $model->getAttribute($attribute);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    /** @param array<string, mixed> $changes */
    private function description(Model $model, string $event, ?string $subjectName, array $changes): string
    {
        $name = $subjectName ?? 'объект';

        if ($model instanceof GuildMember) {
            if ($event === 'created') {
                return "В гильдию вступил персонаж «{$name}».";
            }

            if ($event === 'deleted') {
                return $this->memberDepartureAction($model) === 'member.excluded'
                    ? "Персонаж «{$name}» исключён из гильдии."
                    : "Персонаж «{$name}» покинул гильдию.";
            }

            if (array_key_exists('guild_role_id', $changes)) {
                $roleName = GuildRole::query()->whereKey($model->guild_role_id)->value('name') ?? 'без роли';

                return "Для персонажа «{$name}» установлена роль «{$roleName}».";
            }

            return "Изменены данные участника «{$name}».";
        }

        if ($model instanceof GuildAuctionBid) {
            return "Сделана ставка {$model->amount} ДКП на лот #{$model->guild_auction_lot_id}.";
        }

        if ($model instanceof GuildAuctionLot && $event === 'updated') {
            return "Лот «{$name}» закрыт.";
        }

        if ($model instanceof EventParticipant) {
            $verb = $event === 'created' ? 'отказался от участия' : 'отменил отказ от участия';

            return "Участник {$verb} в событии «{$name}».";
        }

        $verb = match ($event) {
            'created' => 'Создан',
            'updated' => 'Изменён',
            'deleted' => 'Удалён',
            default => 'Изменён',
        };

        $object = match (true) {
            $model instanceof GuildAuctionLot => 'лот',
            $model instanceof GuildBankItem => 'предмет хранилища',
            $model instanceof GuildBankItemGrant => 'запись о выдаче предмета',
            $model instanceof GuildBankItemTier => 'уровень предметов',
            $model instanceof GuildRole => 'роль',
            $model instanceof Guild => 'информация о гильдии',
            $model instanceof Post => 'пост журнала',
            $model instanceof PostComment => 'комментарий журнала',
            $model instanceof Event => 'событие календаря',
            $model instanceof EventHistory => 'событие',
            default => 'объект',
        };

        return "{$verb} {$object} «{$name}».";
    }

    private function memberDepartureAction(GuildMember $member): string
    {
        $memberUserId = Character::query()
            ->whereKey($member->character_id)
            ->value('user_id');
        $actorUserId = Auth::id();

        return $actorUserId && $memberUserId && (int) $actorUserId !== (int) $memberUserId
            ? 'member.excluded'
            : 'member.left';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function safeValues(array $values): array
    {
        $excluded = [
            'discord_webhook_url',
            'body',
            'description',
            'about_text',
            'charter_text',
            'preview',
        ];

        return collect(Arr::except($values, $excluded))
            ->filter(fn (mixed $value): bool => is_null($value) || is_scalar($value))
            ->map(fn (mixed $value): mixed => is_string($value) && mb_strlen($value) > 255
                ? null
                : $value)
            ->filter(fn (mixed $value): bool => $value !== null)
            ->all();
    }
}
