/**
 * API комментариев к постам гильдии.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface PostComment {
  id: number;
  post_id: number;
  user_id?: number;
  parent_id: number | null;
  replied_to_comment_id?: number | null;
  /** Текст комментария; null если комментарий скрыт модерацией */
  body: string | null;
  /** Комментарий скрыт модерацией — на фронте показывать «скрыто» */
  is_hidden?: boolean;
  author_name: string;
  author_avatar_url: string | null;
  replied_to_author_name: string | null;
  created_at: string;
  depth: number;
  children: PostComment[];
}

export const commentsApi = {
  /**
   * Список комментариев к посту гильдии.
   */
  async getGuildPostComments(guildId: number, postId: number): Promise<PostComment[]> {
    const res = await http.fetchGet<{ data: PostComment[] } | PostComment[]>(
      `/guilds/${guildId}/posts/${postId}/comments`
    );
    throwOnError(res, 'Ошибка загрузки комментариев');
    const data = res.data;
    if (data && typeof data === 'object' && 'data' in data && Array.isArray((data as { data: PostComment[] }).data)) {
      return (data as { data: PostComment[] }).data;
    }
    return Array.isArray(data) ? data : [];
  },

  /**
   * Создание комментария к посту гильдии.
   */
  async createGuildPostComment(
    guildId: number,
    postId: number,
    payload: { body: string; character_id: number; parent_id?: number | null }
  ): Promise<PostComment> {
    const res = await http.fetchPost<{ data: PostComment } | PostComment>(
      `/guilds/${guildId}/posts/${postId}/comments`,
      payload as Record<string, unknown>
    );
    throwOnError(res, 'Ошибка отправки комментария');
    const data = res.data;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: PostComment }).data;
    }
    return data as PostComment;
  },

  /**
   * Редактирование своего комментария.
   */
  async updateGuildPostComment(
    guildId: number,
    postId: number,
    commentId: number,
    payload: { body: string }
  ): Promise<PostComment> {
    const res = await http.fetchPut<{ data: PostComment } | PostComment>(
      `/guilds/${guildId}/posts/${postId}/comments/${commentId}`,
      payload as Record<string, unknown>
    );
    throwOnError(res, 'Ошибка сохранения комментария');
    const data = res.data;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: PostComment }).data;
    }
    return data as PostComment;
  },

  /**
   * Удаление своего комментария.
   */
  async deleteGuildPostComment(guildId: number, postId: number, commentId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(
      `/guilds/${guildId}/posts/${postId}/comments/${commentId}`
    );
    throwOnError(res, 'Ошибка удаления комментария');
  },

  // --- Модерация в админке ---

  /**
   * Список комментариев для модерации (админка).
   * Бэкенд возвращает { data: [...], meta: {...} } — разбираем из res.data.
   * @param params.post_id — фильтр: только комментарии выбранного поста.
   */
  async getAdminComments(params?: { page?: number; per_page?: number; post_id?: number }): Promise<{
    data: AdminPostCommentItem[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
  }> {
    const qs = new URLSearchParams();
    if (params?.page != null) qs.set('page', String(params.page));
    if (params?.per_page != null) qs.set('per_page', String(params.per_page));
    if (params?.post_id != null) qs.set('post_id', String(params.post_id));
    const url = `/admin/comments${qs.toString() ? `?${qs}` : ''}`;
    const res = await http.fetchGet<{ data: AdminPostCommentItem[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>(url);
    throwOnError(res, 'Ошибка загрузки комментариев');
    const body = res.data;
    const list = body && typeof body === 'object' && 'data' in body && Array.isArray(body.data) ? body.data : [];
    const meta = body && typeof body === 'object' && body.meta ? body.meta : { current_page: 1, last_page: 1, per_page: 20, total: 0 };
    return { data: list, meta };
  },

  async hideAdminComment(commentId: number): Promise<AdminPostCommentItem> {
    const res = await http.fetchPost<{ data: AdminPostCommentItem } | AdminPostCommentItem>(
      `/admin/comments/${commentId}/hide`,
      {}
    );
    throwOnError(res, 'Ошибка скрытия комментария');
    const body = res.data;
    if (body && typeof body === 'object' && 'data' in body) return (body as { data: AdminPostCommentItem }).data;
    return body as AdminPostCommentItem;
  },

  async unhideAdminComment(commentId: number): Promise<AdminPostCommentItem> {
    const res = await http.fetchPost<{ data: AdminPostCommentItem } | AdminPostCommentItem>(
      `/admin/comments/${commentId}/unhide`,
      {}
    );
    throwOnError(res, 'Ошибка отображения комментария');
    const body = res.data;
    if (body && typeof body === 'object' && 'data' in body) return (body as { data: AdminPostCommentItem }).data;
    return body as AdminPostCommentItem;
  },

  async deleteAdminComment(commentId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(`/admin/comments/${commentId}`);
    throwOnError(res, 'Ошибка удаления комментария');
  },
};

/** Элемент списка комментариев в админке (модерация). */
export interface AdminPostCommentItem {
  id: number;
  post_id: number;
  body: string | null;
  is_hidden: boolean;
  author_name: string;
  author_avatar_url: string | null;
  created_at: string;
  post_title: string | null;
  guild_id: number | null;
  guild_name: string | null;
}
