<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weapon extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'name',
        'game_id',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
