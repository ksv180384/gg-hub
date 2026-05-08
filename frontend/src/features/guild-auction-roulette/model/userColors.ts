/**
 * Палитра цветов для подсветки разных пользователей в списке участников рулетки.
 *
 * Цвет назначается по порядку появления уникальных user_id в `wheelEntries`,
 * чтобы участники одного пользователя были одного оттенка. Если пользователь
 * представлен на колесе только одним персонажем, подсветка не нужна.
 */
export interface UserColorTheme {
  /** Фон пилюли в светлой теме. */
  bg: string;
  /** Фон пилюли в тёмной теме. */
  bgDark: string;
  /** Цвет рамки. */
  border: string;
  /** Цвет текста. */
  text: string;
  /** Цвет точки-индикатора у имени персонажа в правой панели. */
  dot: string;
}

export const USER_COLOR_PALETTE: readonly UserColorTheme[] = [
  {
    bg: 'rgba(244, 114, 182, 0.18)',
    bgDark: 'rgba(244, 114, 182, 0.28)',
    border: 'rgba(190, 24, 93, 0.55)',
    text: '#9d174d',
    dot: '#ec4899',
  },
  {
    bg: 'rgba(96, 165, 250, 0.18)',
    bgDark: 'rgba(96, 165, 250, 0.28)',
    border: 'rgba(37, 99, 235, 0.55)',
    text: '#1e3a8a',
    dot: '#3b82f6',
  },
  {
    bg: 'rgba(167, 139, 250, 0.20)',
    bgDark: 'rgba(167, 139, 250, 0.30)',
    border: 'rgba(124, 58, 237, 0.55)',
    text: '#5b21b6',
    dot: '#8b5cf6',
  },
  {
    bg: 'rgba(52, 211, 153, 0.18)',
    bgDark: 'rgba(52, 211, 153, 0.28)',
    border: 'rgba(5, 150, 105, 0.55)',
    text: '#065f46',
    dot: '#10b981',
  },
  {
    bg: 'rgba(251, 191, 36, 0.20)',
    bgDark: 'rgba(251, 191, 36, 0.30)',
    border: 'rgba(217, 119, 6, 0.55)',
    text: '#92400e',
    dot: '#f59e0b',
  },
  {
    bg: 'rgba(34, 211, 238, 0.18)',
    bgDark: 'rgba(34, 211, 238, 0.28)',
    border: 'rgba(8, 145, 178, 0.55)',
    text: '#155e75',
    dot: '#06b6d4',
  },
  {
    bg: 'rgba(251, 146, 60, 0.20)',
    bgDark: 'rgba(251, 146, 60, 0.30)',
    border: 'rgba(234, 88, 12, 0.55)',
    text: '#9a3412',
    dot: '#f97316',
  },
  {
    bg: 'rgba(163, 230, 53, 0.22)',
    bgDark: 'rgba(163, 230, 53, 0.32)',
    border: 'rgba(101, 163, 13, 0.55)',
    text: '#3f6212',
    dot: '#84cc16',
  },
  {
    bg: 'rgba(129, 140, 248, 0.20)',
    bgDark: 'rgba(129, 140, 248, 0.30)',
    border: 'rgba(79, 70, 229, 0.55)',
    text: '#3730a3',
    dot: '#6366f1',
  },
  {
    bg: 'rgba(45, 212, 191, 0.18)',
    bgDark: 'rgba(45, 212, 191, 0.28)',
    border: 'rgba(13, 148, 136, 0.55)',
    text: '#115e59',
    dot: '#14b8a6',
  },
];

export function getUserColorByIndex(index: number): UserColorTheme {
  const safeIndex = ((index % USER_COLOR_PALETTE.length) + USER_COLOR_PALETTE.length) %
    USER_COLOR_PALETTE.length;
  return USER_COLOR_PALETTE[safeIndex];
}
