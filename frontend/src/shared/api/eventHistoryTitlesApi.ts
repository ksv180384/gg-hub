/**
 * Подсказки и справочник названий событий истории.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface EventHistoryTitleDto {
  id: number;
  name: string;
  dkp_base_points: number | null;
  distribute_dkp_to_participants?: boolean;
  histories_count?: number;
}

export type SaveEventHistoryTitlePayload = {
  name: string;
  dkp_base_points?: number | null;
  distribute_dkp_to_participants?: boolean;
};

function unwrapTitle(
  raw: EventHistoryTitleDto | { data?: EventHistoryTitleDto } | null,
): EventHistoryTitleDto {
  if (raw && typeof raw === 'object' && 'data' in raw && raw.data) {
    return raw.data;
  }
  return raw as EventHistoryTitleDto;
}

export const eventHistoryTitlesApi = {
  async list(options: { query?: string; limit?: number } = {}): Promise<EventHistoryTitleDto[]> {
    const params: Record<string, string | number> = {};
    if (options.query != null) params.query = options.query;
    if (options.limit != null) params.limit = options.limit;

    const res = await http.fetchGet<{ data: EventHistoryTitleDto[] }>('/event-history-titles', {
      params,
    });
    throwOnError(res, 'Не удалось загрузить виды событий.');
    const raw = res.data as { data?: EventHistoryTitleDto[] } | null;
    return raw?.data ?? [];
  },

  async search(query: string, limit = 10): Promise<EventHistoryTitleDto[]> {
    return this.list({ query, limit });
  },

  async create(payload: SaveEventHistoryTitlePayload): Promise<EventHistoryTitleDto> {
    const res = await http.fetchPost<EventHistoryTitleDto | { data: EventHistoryTitleDto }>(
      '/event-history-titles',
      payload as Record<string, unknown>,
    );
    throwOnError(res, 'Не удалось добавить вид события.');
    return unwrapTitle(res.data as EventHistoryTitleDto | { data?: EventHistoryTitleDto } | null);
  },

  async update(id: number, payload: SaveEventHistoryTitlePayload): Promise<EventHistoryTitleDto> {
    const res = await http.fetchPut<EventHistoryTitleDto | { data: EventHistoryTitleDto }>(
      `/event-history-titles/${id}`,
      payload as Record<string, unknown>,
    );
    throwOnError(res, 'Не удалось сохранить вид события.');
    return unwrapTitle(res.data as EventHistoryTitleDto | { data?: EventHistoryTitleDto } | null);
  },

  async delete(id: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/event-history-titles/${id}`);
    throwOnError(res, 'Не удалось удалить вид события.');
  },
};
