<?php

namespace Domains\GuildBank\Models;

use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Предмет каталога банка (хранилища) гильдии.
 *
 * Описывает тип предмета в гильдии: название, тир, опциональную стоимость в ДКП и остаток
 * на складе. Выдачи участникам фиксируются отдельными записями {@see GuildBankItemGrant};
 * при выдаче quantity уменьшается на 1, если не null. Значение null у quantity означает
 * неограниченный остаток. Списание ДКП при выдаче опирается на dkp_cost только при
 * включённой у гильдии системе ДКП.
 *
 * @property int $id Уникальный идентификатор предмета в каталоге.
 * @property int $guild_id Гильдия-владелец каталога; предмет не переносится между гильдиями.
 * @property string $name Название для списков, карточек и выбора при выдаче.
 * @property string|null $description Дополнительное описание в форме редактирования (необязательно).
 * @property int|null $guild_bank_item_tier_id Ссылка на тир; null — предмет без тира.
 * @property int|null $dkp_cost Стоимость в ДКП при выдаче; null или 0 — без списания очков.
 * @property int|null $quantity Остаток на складе; null — бесконечный остаток (∞ в интерфейсе).
 * @property \Illuminate\Support\Carbon|null $created_at Дата добавления предмета в каталог.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата последнего изменения полей или остатка.
 *
 * @property-read Guild $guild Гильдия, которой принадлежит каталогная позиция.
 * @property-read GuildBankItemTier|null $tier Тир для отображения названия и цвета в UI.
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GuildBankItemGrant> $grants Все выдачи этого предмета, от новых к старым.
 */
class GuildBankItem extends Model
{
    protected $table = 'guild_bank_items';

    protected $fillable = [
        'guild_id',
        'name',
        'description',
        'guild_bank_item_tier_id',
        'dkp_cost',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'guild_bank_item_tier_id' => 'integer',
            'dkp_cost' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** Гильдия, в банке которой заведён предмет. */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /** Тир предмета; при удалении тира у предметов сбрасывается привязка на уровне приложения. */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(GuildBankItemTier::class, 'guild_bank_item_tier_id');
    }

    /**
     * История выдач данного предмета участникам гильдии.
     *
     * @return HasMany<GuildBankItemGrant>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(GuildBankItemGrant::class, 'guild_bank_item_id')->orderByDesc('granted_at');
    }
}
