<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingCtaClick extends Model
{
    protected $fillable = [
        'button',
        'user_agent',
        'ip_address',
    ];
}
