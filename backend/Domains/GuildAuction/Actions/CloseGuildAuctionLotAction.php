<?php

namespace Domains\GuildAuction\Actions;

use App\Actions\Notification\SendGuildDiscordNotificationAction;
use App\Models\Notification;
use App\Models\User;
use App\Services\GuildAuctionSocketBroadcaster;
use App\Services\Notifications\GuildLinkBuilder;
use Domains\Character\Models\Character;
use Domains\Guild\Actions\GetGuildMemberUserIdsWithPermissionAction;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Actions\RecordGuildDkpLedgerEntryAction;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseGuildAuctionLotAction
{
    private const NOTIFY_PERMISSION_SLUGS = [
        'zakryvat-aukcion',
        'dobavliat-predmety-na-aukcion',
    ];

    public function __construct(
        private RecordGuildDkpLedgerEntryAction $recordGuildDkpLedgerEntryAction,
        private GuildAuctionSocketBroadcaster $broadcaster,
        private GetGuildMemberUserIdsWithPermissionAction $getGuildMemberUserIdsWithPermissionAction,
        private SendGuildDiscordNotificationAction $sendGuildDiscordNotificationAction,
        private GuildLinkBuilder $linkBuilder,
    ) {}

    public function __invoke(Guild $guild, GuildAuctionLot $lot, ?User $actor = null): GuildAuctionLot
    {
        $wasClosed = false;
        $winnerNotification = null;
        $closedNotification = null;

        $closed = DB::transaction(function () use ($guild, $lot, $actor, &$wasClosed, &$winnerNotification, &$closedNotification): GuildAuctionLot {
            /** @var GuildAuctionLot $lockedLot */
            $lockedLot = GuildAuctionLot::query()
                ->where('guild_id', $guild->id)
                ->whereKey($lot->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLot->status !== GuildAuctionLot::STATUS_ACTIVE) {
                return $lockedLot;
            }

            $wasClosed = true;
            $lockedLot->status = GuildAuctionLot::STATUS_CLOSED;
            $lockedLot->closed_at = now();
            $lockedLot->closed_by_user_id = $actor?->id;
            $itemName = 'Лот #' . $lockedLot->id;

            if ($lockedLot->current_bid_user_id && $lockedLot->current_bid_amount) {
                $item = GuildBankItem::query()
                    ->where('guild_id', $guild->id)
                    ->whereKey($lockedLot->guild_bank_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $itemName = $item->name;

                if ($item->quantity !== null && (int) $item->quantity <= 0) {
                    throw ValidationException::withMessages(['lot' => 'Предмета больше нет на складе.']);
                }

                $characterId = $lockedLot->current_bid_character_id
                    ? (int) $lockedLot->current_bid_character_id
                    : $this->resolveWinnerCharacterId($guild, (int) $lockedLot->current_bid_user_id);

                $grant = GuildBankItemGrant::query()->create([
                    'guild_id' => $guild->id,
                    'guild_bank_item_id' => $item->id,
                    'received_by_character_id' => $characterId,
                    'granted_by_character_id' => null,
                    'reason' => 'Выкуплено на аукционе',
                    'granted_at' => now(),
                    'dkp_charged' => (int) $lockedLot->current_bid_amount,
                ]);

                ($this->recordGuildDkpLedgerEntryAction)($guild, [
                    'user_id' => (int) $lockedLot->current_bid_user_id,
                    'amount' => -1 * (int) $lockedLot->current_bid_amount,
                    'source' => GuildDkpLedgerSource::Auction,
                    'guild_bank_item_grant_id' => $grant->id,
                    'guild_bank_item_id' => $item->id,
                    'character_id' => $characterId,
                    'actor_user_id' => $actor?->id,
                    'reason' => "Выкуп предмета «{$item->name}» на аукционе",
                ]);

                if ($item->quantity !== null) {
                    $item->quantity = max(0, (int) $item->quantity - 1);
                    $item->save();
                }

                $lockedLot->winner_user_id = $lockedLot->current_bid_user_id;
                $lockedLot->guild_bank_item_grant_id = $grant->id;

                $character = Character::query()->select('id', 'name')->find($characterId);
                $winner = User::query()->select('id', 'name')->find($lockedLot->current_bid_user_id);
                $winnerNotification = [
                    'winner_name' => $character?->name ?? $winner?->name ?? 'Пользователь',
                    'item_name' => $item->name,
                    'amount' => (int) $lockedLot->current_bid_amount,
                    'lot_id' => (int) $lockedLot->id,
                ];
            } else {
                $itemName = GuildBankItem::query()
                    ->where('guild_id', $guild->id)
                    ->whereKey($lockedLot->guild_bank_item_id)
                    ->value('name') ?? $itemName;
            }

            $lockedLot->save();
            $closedNotification = [
                'winner_name' => $winnerNotification['winner_name'] ?? null,
                'item_name' => $itemName,
                'amount' => $winnerNotification['amount'] ?? null,
                'lot_id' => (int) $lockedLot->id,
            ];

            return $lockedLot;
        });

        if ($wasClosed) {
            $this->broadcaster->broadcastChanged($closed);
            if ($winnerNotification !== null) {
                $this->notifyAuctionManagers($guild, $winnerNotification);
            }
            if ($closedNotification !== null) {
                $this->sendDiscordNotification($guild, $closedNotification);
            }
        }

        return $closed;
    }

    private function resolveWinnerCharacterId(Guild $guild, int $userId): int
    {
        $characterId = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $userId))
            ->with('character:id,user_id,is_main')
            ->get()
            ->sortByDesc(fn (GuildMember $member) => (bool) $member->character?->is_main)
            ->first()?->character_id;

        if ($characterId === null) {
            $characterId = Character::query()->where('user_id', $userId)->value('id');
        }

        if ($characterId === null) {
            throw ValidationException::withMessages(['lot' => 'Не найден персонаж победителя для выдачи предмета.']);
        }

        return (int) $characterId;
    }

    /**
     * @param array{winner_name:string,item_name:string,amount:int,lot_id:int} $data
     */
    private function notifyAuctionManagers(Guild $guild, array $data): void
    {
        $userIds = collect();
        foreach (self::NOTIFY_PERMISSION_SLUGS as $permissionSlug) {
            $userIds = $userIds->merge(($this->getGuildMemberUserIdsWithPermissionAction)($guild, $permissionSlug));
        }

        foreach ($userIds->unique()->values() as $userId) {
            Notification::query()->create([
                'user_id' => (int) $userId,
                'message' => "{$data['winner_name']} победил в лоте «{$data['item_name']}» за {$data['amount']} ДКП.",
                'link' => "/guilds/{$guild->id}/auction/lots/{$data['lot_id']}",
            ]);
        }
    }

    /**
     * @param array{winner_name:string|null,item_name:string,amount:int|null,lot_id:int} $data
     */
    private function sendDiscordNotification(Guild $guild, array $data): void
    {
        $url = $this->linkBuilder->auctionLotUrl($guild, $data['lot_id']);
        $message = "Лот «{$data['item_name']}» закрыт.\n";
        if ($data['winner_name'] !== null && $data['amount'] !== null) {
            $message .= "Победитель: {$data['winner_name']}.\n"
                . "Ставка: {$data['amount']} ДКП.\n";
        } else {
            $message .= "Победителя нет.\n";
        }
        $message .= $url;

        ($this->sendGuildDiscordNotificationAction)(
            $guild,
            'discord_notify_auction_lot_closed',
            $message,
        );
    }
}
