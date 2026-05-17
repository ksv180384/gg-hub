<?php

namespace Domains\GuildDkp\Models;

use App\Models\User;
use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Текущий баланс ДКП пользователя в конкретной гильдии.
 *
 * Одна строка на пару (guild_id, user_id). Значение balance обновляется при каждой записи
 * в {@see GuildDkpLedgerEntry} в той же транзакции; допускается отрицательный остаток
 * (например, после выдачи предмета с подтверждением ухода в минус). Если у пользователя
 * несколько персонажей в гильдии, баланс общий для всех них.
 *
 * @property int $id Уникальный идентификатор строки баланса.
 * @property int $guild_id Гильдия, в которой ведётся учёт ДКП.
 * @property int $user_id Пользователь-владелец очков (не персонаж).
 * @property int $balance Текущее количество ДКП; может быть меньше нуля.
 * @property \Illuminate\Support\Carbon|null $created_at Дата первого появления баланса у пользователя в гильдии.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата последнего изменения balance.
 *
 * @property-read Guild $guild Гильдия, к которой относится баланс.
 * @property-read User $user Пользователь, чей остаток ДКП хранится в этой строке.
 */
class GuildUserDkpBalance extends Model
{
    protected $fillable = [
        'guild_id',
        'user_id',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    /** Гильдия, в рамках которой действует этот остаток ДКП. */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /** Пользователь, для которого агрегирован баланс по всем его персонажам в гильдии. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
