<?php

namespace App\Models\Permission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = [
        'name',
        'alias',
    ];

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($role) {
            $role->alias = Str::slug($role->name);
        });
    }
}
