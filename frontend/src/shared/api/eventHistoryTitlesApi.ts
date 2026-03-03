/**
 * Подсказки названий событий истории.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface EventHistoryTitleDto {
  id: number;
  name: string;
}

export const eventHistoryTitlesApi = {
  async search(query: string, limit = 10): Promise<EventHistoryTitleDto[]> {
    const res = await http.fetchGet<{ data: EventHistoryTitleDto[] }>('/event-history-titles', {
      params: { query, limit },
    });
    throwOnError(res, 'Не удалось загрузить варианты названий.');
    const raw = res.data as { data?: EventHistoryTitleDto[] } | null;
    return raw?.data ?? [];
  },

  async update(id: number, name: string): Promise<EventHistoryTitleDto> {
    const res = await http.fetchPut<EventHistoryTitleDto | { data: EventHistoryTitleDto }>(
      `/event-history-titles/${id}`,
      { name }
    );
    throwOnError(res, 'Не удалось сохранить название.');
    const raw = res.data as EventHistoryTitleDto | { data?: EventHistoryTitleDto } | null;
    if (raw && typeof raw === 'object' && 'data' in raw) {
      return (raw as { data: EventHistoryTitleDto }).data!;
    }
    return raw as EventHistoryTitleDto;
  },

  async delete(id: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/event-history-titles/${id}`);
    throwOnError(res, 'Не удалось удалить название.');
  },
};

