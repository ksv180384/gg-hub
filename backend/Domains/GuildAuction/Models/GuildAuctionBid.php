<?php

namespace Domains\GuildAuction\Models;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuildAuctionBid extends Model
{
    protected $fillable = [
        'guild_auction_lot_id',
        'guild_id',
        'user_id',
        'character_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(GuildAuctionLot::class, 'guild_auction_lot_id');
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
