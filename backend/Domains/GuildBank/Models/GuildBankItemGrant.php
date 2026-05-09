<?php

namespace Domains\GuildBank\Models;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Факт выдачи предмета из банка гильдии конкретному персонажу.
 * Нужен для истории: кто получил, когда и по какой причине.
 *
 * @property int $id Уникальный идентификатор выдачи.
 * @property int $guild_id Гильдия, в рамках которой произошла выдача.
 * @property int $guild_bank_item_id Выданный предмет из каталога банка гильдии.
 * @property int $received_by_character_id Персонаж, который получил предмет.
 * @property int|null $granted_by_character_id Персонаж, который выдал предмет (может быть неизвестен/удалён).
 * @property string $reason Причина/основание выдачи (может быть пустой строкой, если не указано).
 * @property \Illuminate\Support\Carbon $granted_at Дата и время выдачи.
 * @property \Illuminate\Support\Carbon|null $created_at Дата создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата обновления записи.
 *
 * @property-read Guild $guild Гильдия, в которой сделана выдача.
 * @property-read GuildBankItem $item Предмет, который был выдан.
 * @property-read Character $receivedByCharacter Персонаж-получатель.
 * @property-read Character|null $grantedByCharacter Персонаж, который выдал (если задан/существует).
 */
class GuildBankItemGrant extends Model
{
    protected $table = 'guild_bank_item_grants';

    protected $fillable = [
        'guild_id',
        'guild_bank_item_id',
        'received_by_character_id',
        'granted_by_character_id',
        'reason',
        'granted_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GuildBankItem::class, 'guild_bank_item_id');
    }

    public function receivedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'received_by_character_id');
    }

    public function grantedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'granted_by_character_id');
    }
}

