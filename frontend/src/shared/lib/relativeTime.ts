/**
 * Переиспользуемое форматирование даты/времени:
 * - до 1 дня: «5 мин назад», «2 ч назад» и т.п.;
 * - от 1 дня: полная дата и время;
 * - при наведении (через компонент RelativeTime) показывается полная дата и время.
 */

const MINUTE_MS = 60 * 1000;
const HOUR_MS = 60 * MINUTE_MS;

/**
 * Нормализует строку даты из API для парсинга.
 * Сервер (Laravel) отдаёт время в UTC (Z или +00:00), хотя в БД часто записано
 * локальное время. Чтобы «X мин/ч назад» считалось верно, убираем признак UTC
 * и парсим как локальное время (15:39 = 15:39 по времени пользователя).
 * - Z или +00:00 / +0000 / -00:00 (UTC) — убираем и парсим как локальное.
 * - Другие смещения (+03:00 и т.д.) не трогаем.
 */
function normalizeDateString(iso: string): string {
  let s = String(iso).trim();
  if (!s) return s;
  if (/[+-]\d{2}:?\d{2}$/.test(s)) {
    const utcOffset = /[+-]00:?00$/;
    if (utcOffset.test(s)) {
      s = s.replace(utcOffset, '');
    } else {
      return s.replace(' ', 'T');
    }
  } else {
    s = s.replace(/(\.\d+)?Z$/i, '');
  }
  return s.replace(' ', 'T');
}

function parseDate(iso: string | undefined | null): Date | null {
  if (!iso || typeof iso !== 'string') return null;
  const normalized = normalizeDateString(iso);
  const d = new Date(normalized);
  return Number.isNaN(d.getTime()) ? null : d;
}

function pluralMinutes(n: number): string {
  if (n === 1) return '1 минуту';
  if (n >= 2 && n <= 4) return `${n} минуты`;
  return `${n} мин`;
}

function pluralHours(n: number): string {
  if (n === 1) return '1 час';
  if (n >= 2 && n <= 4) return `${n} часа`;
  return `${n} ч`;
}

/**
 * Текст для отображения: «только что», «5 мин назад», «2 ч назад» или полная дата (если >= 1 дня).
 * @param timezone — часовой пояс пользователя (например Europe/Moscow); если не передан, используется локальный.
 */
export function formatRelativeTime(iso: string | undefined | null, timezone?: string): string {
  const date = parseDate(iso);
  if (!date) return '';
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();

  if (diffMs < 0) return formatDateTimeFull(iso, timezone);
  if (diffMs < 45 * 1000) return 'только что';
  if (diffMs < MINUTE_MS) return 'менее минуты назад';

  const minutes = Math.floor(diffMs / MINUTE_MS);
  if (minutes < 60) return `${pluralMinutes(minutes)} назад`;

  const hours = Math.floor(diffMs / HOUR_MS);
  if (hours < 24) return `${pluralHours(hours)} назад`;

  return formatDateTimeFull(iso, timezone);
}

/**
 * Полная дата и время для тултипа (при наведении).
 * @param timezone — часовой пояс пользователя (из профиля); если не передан, используется локальный браузера.
 */
export function formatDateTimeFull(iso: string | undefined | null, timezone?: string): string {
  const d = parseDate(iso);
  if (!d) return '';
  const options: Intl.DateTimeFormatOptions = {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  };
  if (timezone) options.timeZone = timezone;
  return new Intl.DateTimeFormat('ru-RU', options).format(d);
}
