import type { GuildAuctionWheelEntry } from '@/shared/lib/useGuildAuctionWheelSocket';

export type WheelEntry = GuildAuctionWheelEntry;

export const WHEEL_SPIN_SEC_MIN = 2;
export const WHEEL_SPIN_SEC_MAX = 60;
export const WHEEL_SPIN_SEC_DEFAULT = 4;

/** Подпись/плейсхолдер пустого колеса (нужен и для логики «не показывать баннер»). */
export const WHEEL_EMPTY_PLACEHOLDER = 'Добавьте участников';
