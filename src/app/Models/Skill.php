<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ru',
        'skill_link_id',
        'image',
//        'type_id',
//        'level',
//        'type_level',
        'skill_type_id',
        'weapon_id',
        'game_id',
//        'cooldown',
//        'mana_cost',
//        'distance',
//        'skill_type',
//        'use_format_id',
//        'description',
//        'updating_at_lvl',
//        'update_lvl',
//        'unlocked_lvl',
//        'update_info',
//        'materials',
//        'skill_has_traits',
//        'is_fury_attack',
//        'skill_lang_id',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }

    public function weapon()
    {
        return $this->belongsTo(Weapon::class, 'weapon_id');
    }

    public function params()
    {
        return $this->hasMany(SkillParam::class, 'skill_id');
    }
}
