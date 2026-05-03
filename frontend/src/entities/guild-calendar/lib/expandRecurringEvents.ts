import type { GuildEvent } from '@/shared/api/eventsApi';
import type { CalendarEvent } from '@/shared/ui';

/**
 * Разворачивает повторяющиеся события в отдельные вхождения по дням в заданном диапазоне.
 */
export function expandRecurringEvents(
  list: GuildEvent[],
  rangeFrom: Date | null,
  rangeTo: Date | null
): CalendarEvent[] {
  if (!rangeFrom || !rangeTo) {
    return list.map((e) => ({
      id: e.id,
      title: e.title,
      starts_at: e.starts_at,
      ends_at: e.ends_at,
    }));
  }
  const from = new Date(rangeFrom.getFullYear(), rangeFrom.getMonth(), rangeFrom.getDate());
  const to = new Date(rangeTo.getFullYear(), rangeTo.getMonth(), rangeTo.getDate());
  const result: CalendarEvent[] = [];

  function dayKey(d: Date): string {
    return (
      d.getFullYear() +
      '-' +
      String(d.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }
  function timePart(iso: string): string {
    const i = iso.indexOf('T');
    return i >= 0 ? iso.slice(i + 1) : '00:00:00.000Z';
  }
  function occurrenceStartsEnds(dayDate: Date, e: GuildEvent): { starts_at: string; ends_at: string } {
    const key = dayKey(dayDate);
    return {
      starts_at: key + 'T' + timePart(e.starts_at),
      ends_at: key + 'T' + (e.ends_at ? timePart(e.ends_at) : '23:59:59.999Z'),
    };
  }

  for (const e of list) {
    const start = new Date(e.starts_at);
    const end = e.ends_at ? new Date(e.ends_at) : new Date(start);
    const recurEnd = e.recurrence_ends_at ? new Date(e.recurrence_ends_at) : null;
    const recurrence = e.recurrence ?? 'once';

    const startDate = new Date(start.getFullYear(), start.getMonth(), start.getDate());
    let effectiveEnd: Date;
    if (recurrence === 'once') {
      effectiveEnd = new Date(end.getFullYear(), end.getMonth(), end.getDate());
      if (startDate <= to && effectiveEnd >= from) {
        result.push({ id: e.id, title: e.title, starts_at: e.starts_at, ends_at: e.ends_at });
      }
      continue;
    }
    effectiveEnd = recurEnd
      ? new Date(recurEnd.getFullYear(), recurEnd.getMonth(), recurEnd.getDate())
      : new Date(to);
    if (effectiveEnd > to) effectiveEnd = new Date(to);
    if (startDate > effectiveEnd) continue;

    if (recurrence === 'daily') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setDate(d.getDate() + 1);
      }
    } else if (recurrence === 'weekly') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setDate(d.getDate() + 7);
      }
    } else if (recurrence === 'monthly') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setMonth(d.getMonth() + 1);
      }
    } else if (recurrence === 'yearly') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setFullYear(d.getFullYear() + 1);
      }
    }
  }
  return result;
}
