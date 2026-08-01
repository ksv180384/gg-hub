<?php

namespace Domains\ConstantParty\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstantPartyStorageLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_ITEM_CREATED = 'item_created';

    public const ACTION_ITEM_DELETED = 'item_deleted';

    public const ACTION_ITEM_RENAMED = 'item_renamed';

    public const ACTION_QUANTITY_CHANGED = 'quantity_changed';

    public const ACTION_ITEM_GRANTED = 'item_granted';

    public const ACTION_GRANT_REVOKED = 'grant_revoked';

    public const ACTION_MEMBER_JOINED = 'member_joined';

    public const ACTION_MEMBER_LEFT = 'member_left';

    public const ACTION_MEMBER_REMOVED = 'member_removed';

    protected $fillable = [
        'constant_party_id',
        'item_id',
        'actor_character_id',
        'recipient_character_id',
        'action',
        'item_name',
        'actor_character_name',
        'recipient_character_name',
        'old_value',
        'new_value',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function constantParty(): BelongsTo
    {
        return $this->belongsTo(ConstantParty::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ConstantPartyStorageItem::class, 'item_id');
    }

    public function actorCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'actor_character_id');
    }

    public function recipientCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'recipient_character_id');
    }
}
