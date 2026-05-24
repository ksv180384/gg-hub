<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuildAuction\GuildAuctionBidResource;
use App\Http\Resources\GuildAuction\GuildAuctionLotResource;
use App\Models\Notification;
use App\Models\User;
use App\Services\GuildAuctionSocketBroadcaster;
use Domains\Character\Models\Character;
use Domains\Guild\Actions\GetUserGuildCharactersAction;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildAuction\Models\GuildAuctionBid;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Actions\GetGuildUserDkpBalanceAction;
use Domains\GuildDkp\Actions\RecordGuildDkpLedgerEntryAction;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuildAuctionController extends Controller
{
    public function __construct(
        private GetGuildUserDkpBalanceAction $getGuildUserDkpBalanceAction,
        private RecordGuildDkpLedgerEntryAction $recordGuildDkpLedgerEntryAction,
        private GuildAuctionSocketBroadcaster $broadcaster,
    ) {}

    public function context(Request $request, Guild $guild): JsonResponse
    {
        abort_unless((bool) ($guild->dkp_enabled ?? false), 404);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $permissionSlugs = app(\Domains\Guild\Actions\GetUserGuildPermissionSlugsAction::class)($user, $guild)->values()->all();

        return response()->json([
            'data' => [
                'my_permission_slugs' => $permissionSlugs,
                'dkp_enabled' => (bool) ($guild->dkp_enabled ?? false),
                'my_dkp_balance' => ($this->getGuildUserDkpBalanceAction)($guild, (int) $user->id),
                'my_characters' => app(GetUserGuildCharactersAction::class)($user, $guild)
                    ->map(fn (Character $character) => [
                        'id' => (int) $character->id,
                        'name' => $character->name,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function index(Guild $guild): AnonymousResourceCollection
    {
        abort_unless((bool) ($guild->dkp_enabled ?? false), 404);

        $lots = GuildAuctionLot::query()
            ->where('guild_id', $guild->id)
            ->with($this->lotRelations())
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('ends_at')
            ->get();

        return GuildAuctionLotResource::collection($lots);
    }

    public function store(Request $request, Guild $guild): AnonymousResourceCollection
    {
        abort_unless((bool) ($guild->dkp_enabled ?? false), 422, 'Система ДКП отключена в этой гильдии.');

        $data = $request->validate([
            'ends_at' => ['required', 'date', 'after:now'],
            'lots' => ['required', 'array', 'min:1', 'max:20'],
            'lots.*.guild_bank_item_id' => ['required', 'integer'],
            'lots.*.start_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $lots = DB::transaction(function () use ($guild, $request, $data) {
            $created = collect();

            foreach ($data['lots'] as $rawLot) {
                $item = GuildBankItem::query()
                    ->where('guild_id', $guild->id)
                    ->whereKey((int) $rawLot['guild_bank_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAuctionStockAvailable($guild, $item);

                $created->push(GuildAuctionLot::query()->create([
                    'guild_id' => $guild->id,
                    'guild_bank_item_id' => $item->id,
                    'created_by_user_id' => $request->user()?->id,
                    'start_price' => (int) ($rawLot['start_price'] ?? $item->dkp_cost ?? 0),
                    'status' => GuildAuctionLot::STATUS_ACTIVE,
                    'ends_at' => Carbon::parse($data['ends_at']),
                ]));
            }

            return $created;
        });

        $this->notifyGuildMembers($guild, $lots->pluck('item.name')->filter()->values()->all());

        $lots->each(fn (GuildAuctionLot $lot) => $this->broadcaster->broadcastChanged($lot));

        $createdLots = GuildAuctionLot::query()
            ->whereIn('id', $lots->pluck('id')->all())
            ->with($this->lotRelations())
            ->orderBy('ends_at')
            ->get();

        return GuildAuctionLotResource::collection($createdLots);
    }

    public function bid(Request $request, Guild $guild, GuildAuctionLot $lot): GuildAuctionLotResource
    {
        if ((int) $lot->guild_id !== (int) $guild->id) {
            abort(404);
        }

        abort_unless((bool) ($guild->dkp_enabled ?? false), 422, 'Система ДКП отключена в этой гильдии.');

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'character_id' => ['nullable', 'integer'],
        ]);

        $updated = DB::transaction(function () use ($guild, $lot, $request, $data): GuildAuctionLot {
            /** @var GuildAuctionLot $lockedLot */
            $lockedLot = GuildAuctionLot::query()
                ->where('guild_id', $guild->id)
                ->whereKey($lot->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLot->status !== GuildAuctionLot::STATUS_ACTIVE || $lockedLot->ends_at->lte(now())) {
                throw ValidationException::withMessages(['amount' => 'Аукцион по этому предмету уже закрыт.']);
            }

            $amount = (int) $data['amount'];
            $minBid = max((int) $lockedLot->start_price, ((int) ($lockedLot->current_bid_amount ?? 0)) + 1);
            if ($amount < $minBid) {
                throw ValidationException::withMessages(['amount' => "Минимальная ставка: {$minBid} ДКП."]);
            }

            $user = $request->user();
            abort_unless($user !== null, 403);
            $characterId = $this->resolveBidCharacterId($guild, (int) $user->id, $data['character_id'] ?? null);
            $balance = ($this->getGuildUserDkpBalanceAction)($guild, (int) $user->id);
            $reserved = (int) GuildAuctionLot::query()
                ->where('guild_id', $guild->id)
                ->where('status', GuildAuctionLot::STATUS_ACTIVE)
                ->where('current_bid_user_id', $user->id)
                ->whereKeyNot($lockedLot->id)
                ->sum('current_bid_amount');

            if ($amount + $reserved > $balance) {
                throw ValidationException::withMessages(['amount' => 'Недостаточно свободных ДКП для этой ставки.']);
            }

            GuildAuctionBid::query()->create([
                'guild_id' => $guild->id,
                'guild_auction_lot_id' => $lockedLot->id,
                'user_id' => $user->id,
                'character_id' => $characterId,
                'amount' => $amount,
            ]);

            $lockedLot->current_bid_amount = $amount;
            $lockedLot->current_bid_user_id = $user->id;
            $lockedLot->current_bid_character_id = $characterId;

            if ($lockedLot->ends_at->diffInSeconds(now(), false) >= -60) {
                $lockedLot->ends_at = $lockedLot->ends_at->copy()->addMinutes(10);
            }

            $lockedLot->save();

            return $lockedLot;
        });

        $this->broadcaster->broadcastChanged($updated);

        return new GuildAuctionLotResource(
            $updated->load($this->lotRelations())
        );
    }

    public function close(Request $request, Guild $guild, GuildAuctionLot $lot): GuildAuctionLotResource
    {
        if ((int) $lot->guild_id !== (int) $guild->id) {
            abort(404);
        }

        $closed = DB::transaction(function () use ($guild, $request, $lot): GuildAuctionLot {
            /** @var GuildAuctionLot $lockedLot */
            $lockedLot = GuildAuctionLot::query()
                ->where('guild_id', $guild->id)
                ->whereKey($lot->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLot->status !== GuildAuctionLot::STATUS_ACTIVE) {
                return $lockedLot;
            }

            $lockedLot->status = GuildAuctionLot::STATUS_CLOSED;
            $lockedLot->closed_at = now();
            $lockedLot->closed_by_user_id = $request->user()?->id;

            if ($lockedLot->current_bid_user_id && $lockedLot->current_bid_amount) {
                $item = GuildBankItem::query()
                    ->where('guild_id', $guild->id)
                    ->whereKey($lockedLot->guild_bank_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($item->quantity !== null && (int) $item->quantity <= 0) {
                    throw ValidationException::withMessages(['lot' => 'Предмета больше нет на складе.']);
                }

                $winnerBalance = ($this->getGuildUserDkpBalanceAction)($guild, (int) $lockedLot->current_bid_user_id);
                if ($winnerBalance < (int) $lockedLot->current_bid_amount) {
                    throw ValidationException::withMessages(['lot' => 'У победителя недостаточно ДКП для выкупа предмета.']);
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
                    'actor_user_id' => $request->user()?->id,
                    'reason' => "Выкуп предмета «{$item->name}» на аукционе",
                ]);

                if ($item->quantity !== null) {
                    $item->quantity = max(0, (int) $item->quantity - 1);
                    $item->save();
                }

                $lockedLot->winner_user_id = $lockedLot->current_bid_user_id;
                $lockedLot->guild_bank_item_grant_id = $grant->id;
            }

            $lockedLot->save();

            return $lockedLot;
        });

        $this->broadcaster->broadcastChanged($closed);

        return new GuildAuctionLotResource(
            $closed->load($this->lotRelations())
        );
    }

    private function lotRelations(): array
    {
        return [
            'item.tier',
            'currentBidUser:id,name',
            'currentBidCharacter:id,name,avatar,use_profile_avatar,user_id',
            'currentBidCharacter.user:id,avatar',
            'winner:id,name',
            'bids.user:id,name',
            'bids.character:id,name,avatar,use_profile_avatar,user_id',
            'bids.character.user:id,avatar',
        ];
    }

    private function resolveBidCharacterId(Guild $guild, int $userId, mixed $rawCharacterId): int
    {
        $characters = app(GetUserGuildCharactersAction::class)(User::query()->findOrFail($userId), $guild)
            ->values();

        if ($characters->isEmpty()) {
            throw ValidationException::withMessages(['character_id' => 'Для ставки нужен персонаж в этой гильдии.']);
        }

        if ($rawCharacterId !== null && $rawCharacterId !== '') {
            $characterId = (int) $rawCharacterId;
            $exists = $characters->contains(fn (Character $character) => (int) $character->id === $characterId);
            if (! $exists) {
                throw ValidationException::withMessages(['character_id' => 'Выбранный персонаж не состоит в этой гильдии.']);
            }

            return $characterId;
        }

        if ($characters->count() > 1) {
            throw ValidationException::withMessages(['character_id' => 'Выберите персонажа, от имени которого делается ставка.']);
        }

        return (int) $characters->first()->id;
    }

    private function assertAuctionStockAvailable(Guild $guild, GuildBankItem $item): void
    {
        if ($item->quantity === null) {
            return;
        }

        $activeLots = (int) GuildAuctionLot::query()
            ->where('guild_id', $guild->id)
            ->where('guild_bank_item_id', $item->id)
            ->where('status', GuildAuctionLot::STATUS_ACTIVE)
            ->lockForUpdate()
            ->count();

        if ($activeLots >= (int) $item->quantity) {
            throw ValidationException::withMessages([
                'lots' => "Все доступные экземпляры предмета «{$item->name}» уже выставлены на аукцион.",
            ]);
        }
    }

    private function resolveWinnerCharacterId(Guild $guild, int $userId): int
    {
        $characterId = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $userId))
            ->whereHas('character', fn ($q) => $q->orderByDesc('is_main'))
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

    private function notifyGuildMembers(Guild $guild, array $itemNames): void
    {
        $names = implode(', ', array_slice($itemNames, 0, 3));
        if (count($itemNames) > 3) {
            $names .= ' и еще ' . (count($itemNames) - 3);
        }

        $userIds = GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character')
            ->with('character:id,user_id')
            ->get()
            ->pluck('character.user_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            if (! User::query()->whereKey($userId)->exists()) {
                continue;
            }

            Notification::query()->create([
                'user_id' => $userId,
                'message' => "В гильдии «{$guild->name}» выставлены предметы на аукцион: {$names}.",
                'link' => "/guilds/{$guild->id}/auction",
            ]);
        }
    }
}
