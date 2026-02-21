/**
 * API тегов (гильдии и персонажи).
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface Tag {
  id: number;
  name: string;
  slug: string;
  is_hidden: boolean;
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
  /** Список тегов. В админке передать include_hidden=1 для скрытых. */
  async getTags(includeHidden = false): Promise<Tag[]> {
    const path = includeHidden ? '/tags?include_hidden=1' : '/tags';
    const res = await http.fetchGet<TagsListResponse | Tag[]>(path);
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

  async updateTag(id: number, payload: { name?: string; slug?: string; is_hidden?: boolean }): Promise<Tag> {
    const res = await http.fetchPut<TagsListResponse | Tag>(`/tags/${id}`, payload);
    throwOnError(res, 'Ошибка обновления тега');
    return unwrapData(res, {} as Tag) as Tag;
  },

  async deleteTag(id: number): Promise<void> {
    const res = await http.fetchDelete(`/tags/${id}`);
    throwOnError(res, 'Ошибка удаления тега');
  },
};
