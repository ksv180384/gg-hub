<?php

namespace Domains\Event\Models;

use Domains\Guild\Models\Guild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Историческое событие гильдии (проведённый и зафиксированный ивент).
 * Здесь же хранится DKP-часть (если включена у гильдии): базовые очки события.
 *
 * @property int $id Уникальный идентификатор записи события.
 * @property int $guild_id Гильдия, к которой относится событие.
 * @property int|null $event_history_title_id Ссылка на справочник названий (если используется).
 * @property string|null $description Описание/заметки по событию.
 * @property \Illuminate\Support\Carbon|null $occurred_at Дата/время проведения.
 * @property int|null $dkp_base_points Базовые очки ДКП для события (если ДКП включена).
 * @property \Illuminate\Support\Carbon|null $created_at Дата создания записи.
 * @property \Illuminate\Support\Carbon|null $updated_at Дата обновления записи.
 *
 * @property-read Guild $guild Гильдия события.
 * @property-read EventHistoryTitle|null $titleReference Справочник/шаблон заголовка события.
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventHistoryParticipant> $participants Участники события.
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventHistoryScreenshot> $screenshots Скриншоты/вложения события.
 */
class EventHistory extends Model
{
    protected $fillable = [
        'guild_id',
        'event_history_title_id',
        'description',
        'occurred_at',
        'dkp_base_points',
        'distribute_dkp_to_participants',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'dkp_base_points' => 'integer',
            'distribute_dkp_to_participants' => 'boolean',
        ];
    }

    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }

    public function titleReference(): BelongsTo
    {
        return $this->belongsTo(EventHistoryTitle::class, 'event_history_title_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventHistoryParticipant::class);
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(EventHistoryScreenshot::class);
    }
}

