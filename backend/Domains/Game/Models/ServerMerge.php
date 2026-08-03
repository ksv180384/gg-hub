<?php

namespace Domains\Game\Models;

use Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMerge extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STAGE_CHARACTERS = 'characters';

    public const STAGE_GUILDS = 'guilds';

    public const STAGE_CONSTANT_PARTIES = 'constant_parties';

    public const STAGE_SERVER_GROUPS = 'server_groups';

    public const STAGE_FINALIZING = 'finalizing';

    protected $fillable = [
        'game_id',
        'localization_id',
        'target_server_id',
        'requested_by_user_id',
        'source_server_ids',
        'status',
        'current_stage',
        'total_records',
        'processed_records',
        'progress',
        'error_message',
        'started_at',
        'finished_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_server_ids' => 'array',
            'progress' => 'array',
            'total_records' => 'integer',
            'processed_records' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function localization(): BelongsTo
    {
        return $this->belongsTo(Localization::class);
    }

    public function targetServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'target_server_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
