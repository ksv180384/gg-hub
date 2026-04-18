/**
 * API тегов (гильдии и персонажи).
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

/** Права в админке: редактирование / скрытие / удаление тега. */
export const PERMISSION_TAG_EDIT = 'redaktirovat-teg';
export const PERMISSION_TAG_HIDE = 'skryvat-teg';
export const PERMISSION_TAG_DELETE = 'udaliat-teg';

export interface Tag {
  id: number;
  name: string;
  slug: string;
  is_hidden: boolean;
  used_by_user_id?: number | null;
  used_by_guild_id?: number | null;
  created_by_user_id?: number | null;
  used_by?: { id: number; name: string } | null;
  created_by?: { id: number; name: string } | null;
}

export interface TagsListResponse {
  data: Tag[];
}

function unwrapData<T>(res: { data: unknown }, fallback: T): T {
  const raw = res.data as { data?: T } | T | null;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data ?? fallback;
  return (raw as T) ?? fallback;
}

export const tagsApi = {
  /** Список тегов. В админке передать include_hidden=1 для скрытых. `guildId` — подмешать теги этой гильдии (участник). */
  async getTags(includeHidden = false, guildId?: number): Promise<Tag[]> {
    const res = await http.fetchGet<TagsListResponse | Tag[]>('/tags', {
      params: {
        ...(includeHidden ? { include_hidden: 1 } : {}),
        ...(guildId != null && !Number.isNaN(guildId) ? { guild_id: guildId } : {}),
      },
    });
    throwOnError(res, 'Ошибка загрузки тегов');
    const data = res.data;
    if (Array.isArray((data as TagsListResponse)?.data)) {
      return (data as TagsListResponse).data;
    }
    return Array.isArray(data) ? data : [];
  },

  async createTag(payload: { name: string; slug?: string }): Promise<Tag> {
    const res = await http.fetchPost<TagsListResponse | Tag>('/tags', payload);
    throwOnError(res, 'Ошибка создания тега');
    return unwrapData(res, {} as Tag) as Tag;
  },

  /** Тег гильдии: used_by_guild_id заполнен, used_by_user_id — нет. Право dobavliat-teg-gildii. */
  async createGuildTag(guildId: number, payload: { name: string; slug?: string }): Promise<Tag> {
    const res = await http.fetchPost<TagsListResponse | Tag>(`/guilds/${guildId}/tags`, payload);
    throwOnError(res, 'Ошибка создания тега гильдии');
    return unwrapData(res, {} as Tag) as Tag;
  },

  async updateTag(id: number, payload: { name?: string; slug?: string; is_hidden?: boolean }): Promise<Tag> {
    const res = await http.fetchPut<TagsListResponse | Tag>(`/tags/${id}`, payload);
    throwOnError(res, 'Ошибка обновления тега');
    return unwrapData(res, {} as Tag) as Tag;
  },

  async deleteTag(id: number): Promise<void> {
    const res = await http.fetchDelete(`/tags/${id}`);
    throwOnError(res, 'Ошибка удаления тега');
  },

  /** Удаление тега гильдии (used_by_guild_id). Право udaliat-teg-gildii. */
  async deleteGuildTag(guildId: number, tagId: number): Promise<void> {
    const res = await http.fetchDelete(`/guilds/${guildId}/tags/${tagId}`);
    throwOnError(res, 'Ошибка удаления тега гильдии');
  },
};
