<?php

namespace Domains\GuildBank\Models;

use Domains\Character\Models\Character;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Факт выдачи предмета из банка гильдии конкретному персонажу.
 *
 * Фиксирует, кто получил предмет, когда, по какой причине и сколько ДКП списано на момент
 * выдачи (dkp_charged). Пока запись существует, предмет считается выданным участнику;
 * отмена выдачи удаляет грант, возвращает остаток на склад и при ненулевом dkp_charged
 * создаёт обратное движение в журнале ДКП. Удаление предмета из каталога запрещено,
 * пока есть активные выдачи.
 *
 * @property int $id Уникальный идентификатор выдачи.
 * @property int $guild_id Гильдия, в рамках которой зафиксирована выдача.
 * @property int $guild_bank_item_id Предмет каталога, который был выдан.
 * @property int $received_by_character_id Персонаж-получатель; ДКП списывается с user_id владельца персонажа.
 * @property int|null $granted_by_character_id Персонаж офицера, оформившего выдачу (необязательно).
 * @property string $reason Основание выдачи; пустая строка, если в форме не заполнено.
 * @property \Illuminate\Support\Carbon $granted_at Дата и время выдачи для истории и журнала ДКП.
 * @property int|null $dkp_charged Фактически списанные ДКП при выдаче; снимок на момент операции, не текущий dkp_cost предмета.
 * @property \Illuminate\Support\Carbon|null $created_at Дата создания записи в БД.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата последнего обновления записи.
 *
 * @property-read Guild $guild Гильдия, в которой оформлена выдача.
 * @property-read GuildBankItem $item Каталогная позиция, с которой связана выдача.
 * @property-read Character $receivedByCharacter Персонаж, которому выдан предмет.
 * @property-read Character|null $grantedByCharacter Персонаж, от имени которого оформлена выдача, если указан.
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
        'dkp_charged',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'dkp_charged' => 'integer',
        ];
    }

    /** Гильдия, чей банк и журнал выдач содержат эту запись. */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /** Предмет каталога, количество и стоимость которого учитывались при выдаче. */
    public function item(): BelongsTo
    {
        return $this->belongsTo(GuildBankItem::class, 'guild_bank_item_id');
    }

    /** Персонаж участника гильдии, получивший предмет. */
    public function receivedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'received_by_character_id');
    }

    /** Персонаж, указанный как выдавший предмет; может отсутствовать. */
    public function grantedByCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'granted_by_character_id');
    }
}
