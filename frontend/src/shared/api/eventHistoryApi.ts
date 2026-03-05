/**
 * API истории событий гильдии.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface EventHistoryParticipantDto {
  id: number;
  character_id: number | null;
  external_name: string | null;
  character?: { id: number; name: string } | null;
}

export interface EventHistoryScreenshotDto {
  id: number;
  url: string;
  title: string | null;
  sort_order: number;
}

export interface EventHistoryItem {
  id: number;
  guild_id: number;
  title: string;
  description: string | null;
  occurred_at: string | null;
  participants?: EventHistoryParticipantDto[];
  screenshots?: EventHistoryScreenshotDto[];
  created_at: string | null;
  updated_at: string | null;
}

export interface CreateEventHistoryPayload {
  title: string;
  description?: string | null;
  occurred_at?: string | null;
  participants?: { character_id?: number | null; external_name?: string | null }[];
  screenshots?: { url: string; title?: string | null; sort_order?: number | null }[];
}

export interface UpdateEventHistoryPayload extends Partial<CreateEventHistoryPayload> {}

export const eventHistoryApi = {
  async list(guildId: number, params?: { page?: number; per_page?: number }): Promise<EventHistoryItem[]> {
    const query: Record<string, number> = {};
    if (params?.page != null) query.page = params.page;
    if (params?.per_page != null) query.per_page = params.per_page;

    const res = await http.fetchGet<{ data: EventHistoryItem[] } | EventHistoryItem[]>(
      `/guilds/${guildId}/event-history`,
      Object.keys(query).length ? { params: query } : undefined
    );
    throwOnError(res, 'Не удалось загрузить историю событий.');
    const raw = res.data as { data?: EventHistoryItem[] } | EventHistoryItem[] | null;
    if (raw && Array.isArray((raw as { data?: EventHistoryItem[] }).data)) {
      return (raw as { data: EventHistoryItem[] }).data;
    }
    return (raw as EventHistoryItem[]) ?? [];
  },

  async get(guildId: number, id: number): Promise<EventHistoryItem> {
    const res = await http.fetchGet<EventHistoryItem | { data: EventHistoryItem }>(
      `/guilds/${guildId}/event-history/${id}`
    );
    throwOnError(res, 'Событие не найдено.');
    const raw = res.data as EventHistoryItem | { data?: EventHistoryItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) {
      return (raw as { data: EventHistoryItem }).data!;
    }
    return raw as EventHistoryItem;
  },

  async create(guildId: number, payload: CreateEventHistoryPayload): Promise<EventHistoryItem> {
    const res = await http.fetchPost<EventHistoryItem | { data: EventHistoryItem }>(
      `/guilds/${guildId}/event-history`,
      payload as Record<string, unknown>
    );
    throwOnError(res, 'Не удалось создать событие.');
    const raw = res.data as EventHistoryItem | { data?: EventHistoryItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) {
      return (raw as { data: EventHistoryItem }).data!;
    }
    return raw as EventHistoryItem;
  },

  async update(
    guildId: number,
    id: number,
    payload: UpdateEventHistoryPayload
  ): Promise<EventHistoryItem> {
    const res = await http.fetchPut<EventHistoryItem | { data: EventHistoryItem }>(
      `/guilds/${guildId}/event-history/${id}`,
      payload as Record<string, unknown>
    );
    throwOnError(res, 'Не удалось сохранить событие.');
    const raw = res.data as EventHistoryItem | { data?: EventHistoryItem } | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) {
      return (raw as { data: EventHistoryItem }).data!;
    }
    return raw as EventHistoryItem;
  },

  async delete(guildId: number, id: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/guilds/${guildId}/event-history/${id}`);
    throwOnError(res, 'Не удалось удалить событие.');
  },
};

