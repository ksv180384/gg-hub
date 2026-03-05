/**
 * API событий гильдии (календарь).
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type EventRecurrence = 'once' | 'daily' | 'weekly' | 'monthly' | 'yearly';

export interface GuildEvent {
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
  created_at: string;
  updated_at: string;
}

export interface CreateEventPayload {
  character_id: number;
  title: string;
  description?: string | null;
  starts_at: string;
  ends_at?: string | null;
  recurrence?: EventRecurrence | null;
  recurrence_ends_at?: string | null;
}

export interface UpdateEventPayload extends Partial<CreateEventPayload> {}

function formatDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

export const eventsApi = {
  async list(guildId: number, from: Date, to: Date): Promise<GuildEvent[]> {
    const res = await http.fetchGet<{ data: GuildEvent[] }>(
      `/guilds/${guildId}/events?from=${formatDate(from)}&to=${formatDate(to)}`
    );
    throwOnError(res, 'Не удалось загрузить события.');
    const data = res.data as { data?: GuildEvent[] };
    return Array.isArray(data?.data) ? data.data : (data as unknown as GuildEvent[]);
  },

  async get(guildId: number, eventId: number): Promise<GuildEvent> {
    const res = await http.fetchGet<GuildEvent>(`/guilds/${guildId}/events/${eventId}`);
    throwOnError(res, 'Событие не найдено.');
    return res.data!;
  },

  async create(guildId: number, payload: CreateEventPayload): Promise<GuildEvent> {
    const res = await http.fetchPost<GuildEvent>(`/guilds/${guildId}/events`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось создать событие.');
    return res.data!;
  },

  async update(guildId: number, eventId: number, payload: UpdateEventPayload): Promise<GuildEvent> {
    const res = await http.fetchPut<GuildEvent>(`/guilds/${guildId}/events/${eventId}`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось сохранить изменения.');
    return res.data!;
  },

  async delete(guildId: number, eventId: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/guilds/${guildId}/events/${eventId}`);
    if (res.status >= 400) {
      throwOnError(res, 'Не удалось удалить событие.');
    }
  },
};
