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
  body: string;
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
};
