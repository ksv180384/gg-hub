<?php

namespace App\Console\Commands;

use App\Actions\Notification\SendGuildDiscordNotificationAction;
use App\Services\Notifications\GuildLinkBuilder;
use Domains\Event\Enums\EventRecurrence;
use Domains\Event\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Каждую минуту находит события гильдий, чей ближайший старт попадает
 * в окно [now+9 мин .. now+10 мин], и шлёт в их Discord-вебхуки оповещение
 * «Начало гильдейского события (за 10 мин)».
 *
 * Учитывает повторяющиеся события (daily/weekly/monthly/yearly):
 * - вычисляет точку «следующего старта» относительно текущего времени;
 * - проверяет ограничение `recurrence_ends_at`;
 * - дедуп по конкретному запуску (event_id + время старта), TTL 30 мин,
 *   чтобы повторяющееся событие срабатывало каждый день/неделю и т.д.
 */
class NotifyDiscordEventsStartingCommand extends Command
{
    protected $signature = 'discord:notify-events-starting';

    protected $description = 'Отправить в Discord оповещения о гильдейских событиях, начинающихся через 10 минут (с учётом повторений)';

    public function __invoke(
        SendGuildDiscordNotificationAction $sendGuildDiscordNotificationAction,
        GuildLinkBuilder $linkBuilder,
    ): int {
        $now = Carbon::now();
        $windowStart = $now->copy()->addMinutes(9);
        $windowEnd = $now->copy()->addMinutes(10);

        $events = Event::query()
            ->where(function ($q) use ($windowStart, $windowEnd): void {
                // Не повторяющиеся: попадают в окно по самому starts_at.
                $q->where(function ($q) use ($windowStart, $windowEnd): void {
                    $q->where(function ($q): void {
                        $q->whereNull('recurrence')
                            ->orWhere('recurrence', EventRecurrence::Once->value);
                    })->whereBetween('starts_at', [$windowStart, $windowEnd]);
                })
                // Повторяющиеся: starts_at <= конец окна (т.е. событие уже могло начать повторяться).
                ->orWhere(function ($q) use ($windowEnd): void {
                    $q->whereIn('recurrence', [
                        EventRecurrence::Daily->value,
                        EventRecurrence::Weekly->value,
                        EventRecurrence::Monthly->value,
                        EventRecurrence::Yearly->value,
                    ])->where('starts_at', '<=', $windowEnd);
                });
            })
            // Если задано recurrence_ends_at и оно уже прошло — событие больше не повторяется.
            ->where(function ($q) use ($now): void {
                $q->whereNull('recurrence_ends_at')
                    ->orWhere('recurrence_ends_at', '>=', $now);
            })
            ->whereHas('guild', function ($q): void {
                $q->whereNotNull('discord_webhook_url')
                    ->where('discord_notify_event_starting', true);
            })
            ->with(['guild.game'])
            ->get();

        if ($events->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($events as $event) {
            $guild = $event->guild;
            if (! $guild) {
                continue;
            }

            $startsAt = $event->starts_at instanceof Carbon
                ? $event->starts_at->copy()
                : Carbon::parse((string) $event->starts_at);

            $nextStart = $this->computeNextStart($startsAt, (string) ($event->recurrence ?? ''), $windowStart);
            if ($nextStart === null) {
                continue;
            }

            // Окончание повторений: следующий старт не может быть позже recurrence_ends_at.
            if ($event->recurrence_ends_at) {
                $endsAt = $event->recurrence_ends_at instanceof Carbon
                    ? $event->recurrence_ends_at
                    : Carbon::parse((string) $event->recurrence_ends_at);
                if ($nextStart->greaterThan($endsAt)) {
                    continue;
                }
            }

            // В окно [now+9, now+10] попал?
            if ($nextStart->lessThan($windowStart) || $nextStart->greaterThan($windowEnd)) {
                continue;
            }

            // Дедуп по конкретному запуску: event_id + момент старта.
            $cacheKey = 'discord:event-starting:' . $event->id . ':' . $nextStart->format('YmdHi');
            if (! Cache::add($cacheKey, true, now()->addMinutes(30))) {
                continue;
            }

            $url = $linkBuilder->eventUrl($guild, (int) $event->id);
            $title = trim((string) $event->title);
            $titleLine = $title !== '' ? "«{$title}»" : '#' . $event->id;
            $message = "Через 10 минут начнётся событие: {$titleLine} (старт в "
                . $nextStart->format('H:i') . ")\n{$url}";

            ($sendGuildDiscordNotificationAction)(
                $guild,
                'discord_notify_event_starting',
                $message,
            );
        }

        return self::SUCCESS;
    }

    /**
     * Вычисляет ближайший старт события >= $threshold с учётом recurrence.
     * Возвращает null, если событие не повторяется и старт уже в прошлом.
     */
    private function computeNextStart(Carbon $startsAt, string $recurrence, Carbon $threshold): ?Carbon
    {
        if ($recurrence === '' || $recurrence === EventRecurrence::Once->value) {
            return $startsAt->copy();
        }

        // Если первый старт ещё не наступил — ближайший = он сам.
        if ($startsAt->greaterThanOrEqualTo($threshold)) {
            return $startsAt->copy();
        }

        return match ($recurrence) {
            EventRecurrence::Daily->value => $this->advanceBy($startsAt, $threshold, fn (Carbon $d, int $n) => $d->addDays($n)),
            EventRecurrence::Weekly->value => $this->advanceBy($startsAt, $threshold, fn (Carbon $d, int $n) => $d->addWeeks($n)),
            EventRecurrence::Monthly->value => $this->advanceBy($startsAt, $threshold, fn (Carbon $d, int $n) => $d->addMonthsNoOverflow($n)),
            EventRecurrence::Yearly->value => $this->advanceBy($startsAt, $threshold, fn (Carbon $d, int $n) => $d->addYearsNoOverflow($n)),
            default => null,
        };
    }

    /**
     * Возвращает первый момент старта, не раньше $threshold, перенося $startsAt
     * на N единиц повторения (день/неделя/месяц/год) с помощью $advance().
     */
    private function advanceBy(Carbon $startsAt, Carbon $threshold, callable $advance): Carbon
    {
        $candidate = $startsAt->copy();
        // Защитный лимит — повторения максимум на ~10 лет вперёд (>= ~3650 дней).
        $maxIterations = 4000;
        $iteration = 0;
        while ($candidate->lessThan($threshold) && $iteration < $maxIterations) {
            $candidate = $advance($candidate->copy(), 1);
            $iteration++;
        }

        return $candidate;
    }
}
