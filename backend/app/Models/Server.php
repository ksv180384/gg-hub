<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'localization_id',
        'name',
        'slug',
        'is_active',
        'merged_into_server_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    /** Сервер, в который был объединён этот (после merge). */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'merged_into_server_id');
    }
}
