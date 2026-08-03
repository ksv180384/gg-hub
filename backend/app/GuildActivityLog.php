<?php

declare(strict_types=1);

namespace App;

use App\Core\Traits\HasFilter;
use Database\Factories\GuildActivityLogFactory;
use Domains\Guild\Models\Guild;
use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuildActivityLog extends Model
{
    /** @use HasFactory<GuildActivityLogFactory> */
    use HasFactory;

    use HasFilter;

    public const UPDATED_AT = null;

    public const CATEGORY_AUCTION = 'auction';

    public const CATEGORY_ROULETTE = 'roulette';

    public const CATEGORY_STORAGE = 'storage';

    public const CATEGORY_MEMBERS = 'members';

    public const CATEGORY_ACCESS = 'access';

    public const CATEGORY_GUILD = 'guild';

    public const CATEGORY_JOURNAL = 'journal';

    public const CATEGORY_EVENTS = 'events';

    public const CATEGORIES = [
        self::CATEGORY_AUCTION,
        self::CATEGORY_ROULETTE,
        self::CATEGORY_STORAGE,
        self::CATEGORY_MEMBERS,
        self::CATEGORY_ACCESS,
        self::CATEGORY_GUILD,
        self::CATEGORY_JOURNAL,
        self::CATEGORY_EVENTS,
    ];

    protected $fillable = [
        'guild_id',
        'actor_user_id',
        'actor_name',
        'category',
        'action',
        'subject_type',
        'subject_id',
        'subject_name',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
