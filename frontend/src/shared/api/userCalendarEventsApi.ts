/**
 * API: события календаря пользователя по всем его гильдиям.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';
import type { EventRecurrence } from '@/shared/api/eventsApi';

export interface UserCalendarEventItem {
  id: number;
  guild_id: number;
  created_by_character_id: number | null;
  title: string;
  description: string | null;
  starts_at: string;
  ends_at: string | null;
  recurrence: EventRecurrence | null;
  recurrence_ends_at: string | null;
  creator?: { id: number; name: string };
  declined_characters?: { id: number; name: string }[];
  my_declined?: boolean;
  created_at: string;
  updated_at: string;
  guild: { id: number; name: string } | null;
  game: { id: number; name: string } | null;
}

function formatDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

export const userCalendarEventsApi = {
  async listToday(): Promise<UserCalendarEventItem[]> {
    const res = await http.fetchGet<{ data: UserCalendarEventItem[] }>(
      '/user/guild-calendar-events'
    );
    throwOnError(res, 'Не удалось загрузить события.');
    const data = res.data as { data?: UserCalendarEventItem[] } | null;
    return Array.isArray(data?.data) ? data!.data! : (res.data as unknown as UserCalendarEventItem[]);
  },

  async list(from: Date, to: Date): Promise<UserCalendarEventItem[]> {
    const res = await http.fetchGet<{ data: UserCalendarEventItem[] }>(
      `/user/guild-calendar-events?from=${formatDate(from)}&to=${formatDate(to)}`
    );
    throwOnError(res, 'Не удалось загрузить события.');
    const data = res.data as { data?: UserCalendarEventItem[] } | null;
    return Array.isArray(data?.data) ? data!.data! : (res.data as unknown as UserCalendarEventItem[]);
  },
};

