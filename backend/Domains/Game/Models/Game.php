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
        'max_classes_per_character',
        'party_size',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_classes_per_character' => 'integer',
            'party_size' => 'integer',
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
