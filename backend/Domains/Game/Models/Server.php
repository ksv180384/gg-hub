<?php

namespace Domains\Game\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'localization_id',
        'name',
        'slug',
        'is_active',
        'is_merging',
        'merged_into_server_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_merging' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Server $server): void {
            $server->ensureMergeIsNotRunning();
        });

        static::deleting(function (Server $server): void {
            $server->ensureMergeIsNotRunning();
        });
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function localization(): BelongsTo
    {
        return $this->belongsTo(Localization::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'merged_into_server_id');
    }

    private function ensureMergeIsNotRunning(): void
    {
        if ($this->getOriginal('is_merging')) {
            throw ValidationException::withMessages([
                'server' => 'Сервер находится в процессе объединения. Изменение временно недоступно.',
            ]);
        }
    }
}
