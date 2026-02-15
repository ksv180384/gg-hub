<?php

namespace Domains\Game\Models;

use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function localizations(): HasMany
    {
        return $this->hasMany(Localization::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }
}
