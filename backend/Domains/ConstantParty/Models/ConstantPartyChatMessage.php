<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstantPartyChatMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'constant_party_id',
        'character_id',
        'body',
    ];

    public function constantParty(): BelongsTo
    {
        return $this->belongsTo(ConstantParty::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ConstantPartyChatMessageReceipt::class, 'message_id');
    }

    public function scopeWithReceiptSummary(Builder $query): Builder
    {
        return $query->withCount([
            'receipts as recipient_count',
            'receipts as delivered_count' => fn (Builder $query) => $query->whereNotNull('delivered_at'),
            'receipts as read_count' => fn (Builder $query) => $query->whereNotNull('read_at'),
        ]);
    }

    public function deliveryStatus(): string
    {
        $recipientCount = (int) ($this->recipient_count ?? 0);
        if ($recipientCount === 0) {
            return 'sent';
        }
        if ((int) ($this->read_count ?? 0) >= $recipientCount) {
            return 'read';
        }
        if ((int) ($this->delivered_count ?? 0) >= $recipientCount) {
            return 'delivered';
        }

        return 'sent';
    }
}
