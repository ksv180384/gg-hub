<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkillParam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'info',
        'info_original',
        'skill_id',
    ];

    protected $casts = [
        'info' => 'array',
        'info_original' => 'array',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

}
