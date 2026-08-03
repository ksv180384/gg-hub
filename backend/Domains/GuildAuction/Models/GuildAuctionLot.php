<?php

namespace Domains\GuildAuction\Models;

use App\Core\Traits\HasFilter;
use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuildAuctionLot extends Model
{
    use HasFilter;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'guild_id',
        'guild_bank_item_id',
        'quantity',
        'stock_reserved_at',
        'created_by_user_id',
        'closed_by_user_id',
        'winner_user_id',
        'guild_bank_item_grant_id',
        'start_price',
        'current_bid_amount',
        'current_bid_user_id',
        'current_bid_character_id',
        'status',
        'ends_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stock_reserved_at' => 'datetime',
            'start_price' => 'integer',
            'current_bid_amount' => 'integer',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GuildBankItem::class, 'guild_bank_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function currentBidUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_bid_user_id');
    }

    public function currentBidCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'current_bid_character_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(GuildBankItemGrant::class, 'guild_bank_item_grant_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(GuildAuctionBid::class, 'guild_auction_lot_id')->latest();
    }
}
