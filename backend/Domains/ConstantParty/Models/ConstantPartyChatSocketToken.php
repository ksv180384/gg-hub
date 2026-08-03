<?php

namespace Domains\ConstantParty\Models;

use Illuminate\Database\Eloquent\Model;

class ConstantPartyChatSocketToken extends Model
{
    protected $fillable = [
        'token_hash',
        'constant_party_id',
        'character_id',
        'user_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
