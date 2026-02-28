<?php

namespace Domains\Guild\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuildApplicationFormField extends Model
{
    protected $fillable = [
        'guild_id',
        'name',
        'type',
        'required',
        'sort_order',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }
}
