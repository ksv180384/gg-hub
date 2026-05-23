/**
 * API истории событий гильдии.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface EventHistoryParticipantDto {
  id: number;
  character_id: number | null;
  external_name: string | null;
  dkp?: { coefficient: number; points_override: number | null };
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
  dkp?: {
    base_points: number | null;
    distribute_to_participants?: boolean;
  } | null;
  participants?: EventHistoryParticipantDto[];
  screenshots?: EventHistoryScreenshotDto[];
  created_at: string | null;
  updated_at: string | null;
}

export interface CreateEventHistoryPayload {
  title: string;
  description?: string | null;
  occurred_at?: string | null;
  dkp_base_points?: number | null;
  distribute_dkp_to_participants?: boolean;
  participants?: {
    character_id?: number | null;
    external_name?: string | null;
    dkp_coefficient?: number | null;
    dkp_points_override?: number | null;
  }[];
  screenshots?: {
    url?: string;
    file?: File;
    title?: string | null;
    sort_order?: number | null;
  }[];
}

export interface UpdateEventHistoryPayload extends Partial<CreateEventHistoryPayload> {}

function hasScreenshotFiles(payload: CreateEventHistoryPayload | UpdateEventHistoryPayload): boolean {
  return typeof File !== 'undefined'
    && (payload.screenshots ?? []).some((screenshot) => screenshot.file instanceof File);
}

function appendFormValue(formData: FormData, key: string, value: unknown): void {
  if (value === undefined) return;
  if (value === null) {
    formData.append(key, '');
    return;
  }
  if (typeof File !== 'undefined' && value instanceof File) {
    formData.append(key, value);
    return;
  }
  if (typeof value === 'boolean') {
    formData.append(key, value ? '1' : '0');
    return;
  }
  formData.append(key, String(value));
}

function eventHistoryPayloadToFormData(
  payload: CreateEventHistoryPayload | UpdateEventHistoryPayload,
  method?: 'PUT'
): FormData {
  const formData = new FormData();
  if (method) {
    formData.append('_method', method);
  }

  appendFormValue(formData, 'title', payload.title);
  appendFormValue(formData, 'description', payload.description);
  appendFormValue(formData, 'occurred_at', payload.occurred_at);
  appendFormValue(formData, 'dkp_base_points', payload.dkp_base_points);
  appendFormValue(formData, 'distribute_dkp_to_participants', payload.distribute_dkp_to_participants);

  payload.participants?.forEach((participant, index) => {
    appendFormValue(formData, `participants[${index}][character_id]`, participant.character_id);
    appendFormValue(formData, `participants[${index}][external_name]`, participant.external_name);
    appendFormValue(formData, `participants[${index}][dkp_coefficient]`, participant.dkp_coefficient);
    appendFormValue(formData, `participants[${index}][dkp_points_override]`, participant.dkp_points_override);
  });
  if (payload.participants && payload.participants.length === 0) {
    formData.append('participants_empty', '1');
  }

  payload.screenshots?.forEach((screenshot, index) => {
    appendFormValue(formData, `screenshots[${index}][url]`, screenshot.url);
    appendFormValue(formData, `screenshots[${index}][file]`, screenshot.file);
    appendFormValue(formData, `screenshots[${index}][title]`, screenshot.title);
    appendFormValue(formData, `screenshots[${index}][sort_order]`, screenshot.sort_order);
  });

  return formData;
}

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
    const requestPayload = hasScreenshotFiles(payload)
      ? eventHistoryPayloadToFormData(payload)
      : payload as unknown as Record<string, unknown>;
    const res = await http.fetchPost<EventHistoryItem | { data: EventHistoryItem }>(
      `/guilds/${guildId}/event-history`,
      requestPayload
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
    const path = `/guilds/${guildId}/event-history/${id}`;
    const res = hasScreenshotFiles(payload)
      ? await http.fetchPost<EventHistoryItem | { data: EventHistoryItem }>(
        path,
        eventHistoryPayloadToFormData(payload, 'PUT')
      )
      : await http.fetchPut<EventHistoryItem | { data: EventHistoryItem }>(
        path,
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
