/**
 * API оповещений пользователя.
 */

import { http } from '@/shared/api/http';

export interface NotificationItem {
  id: number;
  message: string;
  read_at: string | null;
  created_at: string;
}

export interface NotificationsListResponse {
  data: NotificationItem[];
  unread_count: number;
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export const notificationsApi = {
  async getList(page = 1): Promise<{
    data: NotificationItem[];
    unreadCount: number;
    hasMore: boolean;
    currentPage: number;
  }> {
    const res = await http.fetchGet<NotificationsListResponse>(
      `/notifications?page=${page}`
    );
    if (res.status >= 400) {
      const d = res.data as { message?: string } | null;
      throw new Error(d?.message ?? 'Ошибка загрузки оповещений');
    }
    const raw = res.data as NotificationsListResponse | null;
    if (!raw) return { data: [], unreadCount: 0, hasMore: false, currentPage: 1 };
    return {
      data: raw.data ?? [],
      unreadCount: raw.unread_count ?? 0,
      hasMore: (raw.current_page ?? 1) < (raw.last_page ?? 1),
      currentPage: raw.current_page ?? 1,
    };
  },

  async markAsRead(id: number): Promise<NotificationItem> {
    const res = await http.fetchFull<{ data: NotificationItem }>({
      url: `/notifications/${id}/read`,
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
    });
    if (res.status >= 400) {
      const d = res.data as { message?: string } | null;
      throw new Error(d?.message ?? 'Ошибка');
    }
    const raw = res.data as { data: NotificationItem } | null;
    return raw?.data ?? ({} as NotificationItem);
  },

  async delete(id: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/notifications/${id}`);
    if (res.status >= 400) {
      const d = res.data as { message?: string } | null;
      throw new Error(d?.message ?? 'Ошибка удаления');
    }
  },
};
