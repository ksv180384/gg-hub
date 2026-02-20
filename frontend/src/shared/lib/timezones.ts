/**
 * Список часовых поясов для выбора в профиле.
 * Использует Intl.supportedValuesOf('timeZone') в поддерживаемых средах, иначе — базовый список.
 */
const FALLBACK_TIMEZONES = [
  'Europe/Moscow',
  'Europe/Samara',
  'Asia/Yekaterinburg',
  'Asia/Omsk',
  'Asia/Krasnoyarsk',
  'Asia/Irkutsk',
  'Asia/Yakutsk',
  'Asia/Vladivostok',
  'Europe/Kaliningrad',
  'UTC',
  'Europe/Minsk',
  'Europe/Kyiv',
  'Asia/Almaty',
  'Asia/Tbilisi',
  'Asia/Baku',
  'Asia/Tashkent',
];

export function getTimezones(): string[] {
  if (typeof Intl !== 'undefined' && 'supportedValuesOf' in Intl) {
    try {
      const list = (Intl as unknown as { supportedValuesOf(key: string): string[] }).supportedValuesOf('timeZone');
      return [...new Set([...FALLBACK_TIMEZONES, ...list])].sort();
    } catch {
      return FALLBACK_TIMEZONES;
    }
  }
  return FALLBACK_TIMEZONES;
}

/** Подпись для часового пояса (смещение и название). */
export function getTimezoneLabel(tz: string): string {
  try {
    const formatter = new Intl.DateTimeFormat('ru-RU', {
      timeZone: tz,
      timeZoneName: 'shortOffset',
    });
    const parts = formatter.formatToParts(new Date());
    const tzPart = parts.find((p) => p.type === 'timeZoneName');
    const offset = tzPart?.value ?? '';
    return offset ? `${tz} (${offset})` : tz;
  } catch {
    return tz;
  }
}
