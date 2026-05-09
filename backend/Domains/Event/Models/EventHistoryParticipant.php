<?php

namespace Domains\Event\Models;

use Domains\Character\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Участник исторического события гильдии.
 * Может быть как внутренний персонаж (character_id), так и внешний участник (external_name).
 * Для ДКП хранит коэффициент и ручной override итоговых очков.
 *
 * @property int $id Уникальный идентификатор участника.
 * @property int $event_history_id Ссылка на событие, в котором участвовал.
 * @property int|null $character_id Персонаж из системы (если участник привязан к персонажу).
 * @property string|null $external_name Имя внешнего участника (если не привязан к персонажу).
 * @property string $dkp_coefficient Коэффициент ДКП участника (например 1.00, 0.50, 2.00).
 * @property int|null $dkp_points_override Ручной override итоговых очков (если задан, заменяет расчёт).
 * @property \Illuminate\Support\Carbon|null $created_at Дата создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата обновления записи.
 *
 * @property-read EventHistory $eventHistory Событие, к которому относится участник.
 * @property-read Character|null $character Персонаж участника (если привязан).
 */
class EventHistoryParticipant extends Model
{
    protected $fillable = [
        'event_history_id',
        'character_id',
        'external_name',
        'dkp_coefficient',
        'dkp_points_override',
    ];

    protected function casts(): array
    {
        return [
            'dkp_coefficient' => 'decimal:2',
            'dkp_points_override' => 'integer',
        ];
    }

    public function eventHistory(): BelongsTo
    {
        return $this->belongsTo(EventHistory::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}

