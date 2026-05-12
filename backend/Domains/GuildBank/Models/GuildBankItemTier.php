<?php

namespace Domains\GuildBank\Models;

use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Тир (категория) предметов банка гильдии.
 *
 * Задаёт название и цвет для группировки и отображения предметов в каталоге и на карточках.
 * Тир привязан к одной гильдии; удалить тир можно только если к нему не привязаны предметы.
 * Цвет тира используется в UI (бордер, бейдж), отдельного поля цвета у предмета нет.
 *
 * @property int $id Уникальный идентификатор тира в каталоге гильдии.
 * @property int $guild_id Гильдия, для которой заведён тир.
 * @property string $name Отображаемое название тира в списках и на карточках предметов.
 * @property string|null $color Цвет в формате, принятом в UI (например HEX); null — нейтральное оформление.
 * @property \Illuminate\Support\Carbon|null $created_at Дата создания тира.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата последнего изменения названия или цвета.
 *
 * @property-read Guild $guild Гильдия-владелец справочника тиров.
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GuildBankItem> $items Предметы каталога, привязанные к этому тиру.
 */
class GuildBankItemTier extends Model
{
    protected $table = 'guild_bank_item_tiers';

    protected $fillable = [
        'guild_id',
        'name',
        'color',
    ];

    /** Гильдия, в настройках банка которой создан тир. */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /**
     * Предметы банка, отнесённые к данному тиру.
     *
     * @return HasMany<GuildBankItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GuildBankItem::class, 'guild_bank_item_tier_id');
    }
}
