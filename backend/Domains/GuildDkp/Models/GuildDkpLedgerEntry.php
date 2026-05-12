<?php

namespace Domains\GuildDkp\Models;

use App\Models\User;
use Domains\Character\Models\Character;
use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventHistoryParticipant;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use App\Core\Traits\HasFilter;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись журнала движения ДКП в гильдии.
 *
 * Каждая строка фиксирует одно изменение баланса пользователя: начисление (положительный amount)
 * или списание (отрицательный amount). Запись создаётся в одной транзакции с обновлением
 * {@see GuildUserDkpBalance}; поле balance_after хранит остаток после операции для аудита.
 * Источник операции задаётся source; опциональные внешние ключи связывают запись с событием,
 * выдачей из банка или персонажем-контекстом.
 *
 * @property int $id Уникальный идентификатор записи журнала.
 * @property int $guild_id Гильдия, в рамках которой изменился баланс ДКП.
 * @property int $user_id Пользователь-владелец баланса (ДКП ведётся на аккаунт, не на персонажа).
 * @property int $amount Изменение в очках: положительное — начисление, отрицательное — списание.
 * @property \Illuminate\Support\Carbon $occurred_at Момент операции для отображения в истории (дата выдачи, события или ручной корректировки).
 * @property GuildDkpLedgerSource $source Тип операции: event, manual, bank_grant, bank_grant_revoke.
 * @property int|null $event_history_id Событие истории гильдии, по которому начислены очки (только при source = event).
 * @property int|null $event_history_participant_id Участник события, для которого рассчитано начисление; при пересохранении ДКП события старые записи по event_history_id откатываются.
 * @property int|null $guild_bank_item_grant_id Выдача из банка, при отмене которой создана обратная запись (bank_grant_revoke).
 * @property int|null $guild_bank_item_id Предмет банка, за который списаны или возвращены очки при выдаче/отмене.
 * @property int|null $character_id Персонаж-контекст: получатель при выдаче или участник при ручной корректировке с ростера.
 * @property int|null $actor_user_id Пользователь, выполнивший операцию (выдал предмет, отменил выдачу, вручную изменил баланс).
 * @property string|null $reason Комментарий: основание выдачи, текст ручной корректировки или название события.
 * @property int $balance_after Баланс пользователя в гильдии сразу после применения amount.
 * @property \Illuminate\Support\Carbon|null $created_at Дата создания строки в БД.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата последнего обновления строки.
 *
 * @property-read Guild $guild Гильдия, к которой относится движение ДКП.
 * @property-read User $user Пользователь, чей баланс изменился.
 * @property-read EventHistory|null $eventHistory Событие истории, если начисление привязано к рейду/событию.
 * @property-read EventHistoryParticipant|null $eventHistoryParticipant Строка участника события, по которой рассчитано начисление.
 * @property-read GuildBankItemGrant|null $guildBankItemGrant Выдача предмета, связанная со списанием или возвратом ДКП.
 * @property-read GuildBankItem|null $guildBankItem Предмет из каталога банка, за который изменился баланс.
 * @property-read Character|null $character Персонаж, в контексте которого выполнена операция.
 * @property-read User|null $actorUser Инициатор операции (офицер, выдавший предмет или скорректировавший баланс).
 */
class GuildDkpLedgerEntry extends Model
{
    use HasFilter;

    protected $fillable = [
        'guild_id',
        'user_id',
        'amount',
        'occurred_at',
        'source',
        'event_history_id',
        'event_history_participant_id',
        'guild_bank_item_grant_id',
        'guild_bank_item_id',
        'character_id',
        'actor_user_id',
        'reason',
        'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'occurred_at' => 'datetime',
            'source' => GuildDkpLedgerSource::class,
        ];
    }

    /** Гильдия, в журнале которой зафиксировано движение очков. */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    /** Пользователь, на баланс которого повлияла запись. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Событие из истории гильдии, если очки начислены за участие в нём. */
    public function eventHistory(): BelongsTo
    {
        return $this->belongsTo(EventHistory::class);
    }

    /** Участник события, по данным которого рассчитана сумма начисления. */
    public function eventHistoryParticipant(): BelongsTo
    {
        return $this->belongsTo(EventHistoryParticipant::class);
    }

    /** Выдача предмета из банка, с которой связано списание или возврат ДКП. */
    public function guildBankItemGrant(): BelongsTo
    {
        return $this->belongsTo(GuildBankItemGrant::class);
    }

    /** Предмет каталога банка, указанный в операции выдачи или отмены. */
    public function guildBankItem(): BelongsTo
    {
        return $this->belongsTo(GuildBankItem::class);
    }

    /** Персонаж получателя или контекст ручной операции на ростере. */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** Пользователь, инициировавший списание, возврат или ручную корректировку. */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
