/**
 * API постов пользователя.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface Post {
  id: number;
  user_id: number;
  character_id: number | null;
  guild_id: number | null;
  game_id: number | null;
  title: string | null;
  body: string;
  is_visible_global: boolean;
  is_visible_guild: boolean;
  is_anonymous: boolean;
  is_global_as_guild: boolean;
  status_global: string | null;
  status_guild: string | null;
  is_hidden: boolean;
  published_at_global: string | null;
  published_at_guild: string | null;
  created_at: string;
  updated_at: string;
}

export interface PostsListResponse {
  data: Post[];
}

export interface CreatePostPayload {
  title: string | null;
  body: string;
  character_id: number | null;
  guild_id: number | null;
  game_id: number | null;
  is_visible_global: boolean;
  is_visible_guild: boolean;
  global_visibility_type: 'anonymous' | 'guild' | null;
  status: 'published' | 'draft' | 'hidden';
}

export interface PostResponse {
  data: Post;
}

export const postsApi = {
  async getMyPosts(): Promise<Post[]> {
    const res = await http.fetchGet<PostsListResponse | Post[]>('/user/posts');
    throwOnError(res, 'Ошибка загрузки постов');
    const data = res.data;
    if (data && typeof data === 'object' && 'data' in data && Array.isArray((data as PostsListResponse).data)) {
      return (data as PostsListResponse).data;
    }
    return Array.isArray(data) ? data : [];
  },

  async getPost(id: number): Promise<Post> {
    const res = await http.fetchGet<PostResponse | { data: Post } | Post>(`/user/posts/${id}`);
    throwOnError(res, 'Ошибка загрузки поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  async createPost(payload: CreatePostPayload): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>('/user/posts', payload as unknown as Record<string, unknown>);
    throwOnError(res, 'Ошибка создания поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  async updatePost(id: number, payload: CreatePostPayload): Promise<Post> {
    const res = await http.fetchPut<PostResponse | { data: Post } | Post>(`/user/posts/${id}`, payload as unknown as Record<string, unknown>);
    throwOnError(res, 'Ошибка обновления поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },
};

