<?php

namespace Domains\GuildBank\Models;

use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Предмет банка гильдии (каталог предметов внутри конкретной гильдии).
 *
 * @property int $id Уникальный идентификатор предмета.
 * @property int $guild_id Гильдия-владелец предмета (свой каталог у каждой гильдии).
 * @property string $name Название предмета (отображается в списках/выборе).
 * @property string|null $description Описание/примечания по предмету (необязательно).
 * @property string|null $tier Тир предмета (строковый; используется для маркировки/важности, необязателен).
 * @property string|null $color Цвет предмета (например, для UI/редкости; HEX или любая строка).
 * @property int|null $dkp_cost Стоимость предмета в ДКП (если система ДКП включена у гильдии).
 * @property int|null $quantity Остаток предмета в банке (null — количество не ограничено).
 * @property \Illuminate\Support\Carbon|null $created_at Дата создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата обновления записи.
 *
 * @property-read Guild $guild Гильдия, которой принадлежит предмет.
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GuildBankItemGrant> $grants История выдач этого предмета.
 */
class GuildBankItem extends Model
{
    protected $table = 'guild_bank_items';

    protected $fillable = [
        'guild_id',
        'name',
        'description',
        'tier',
        'color',
        'dkp_cost',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'dkp_cost' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /** @return HasMany<GuildBankItemGrant> */
    public function grants(): HasMany
    {
        return $this->hasMany(GuildBankItemGrant::class, 'guild_bank_item_id')->orderByDesc('granted_at');
    }
}

