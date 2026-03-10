/**
 * API постов пользователя.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface PostCharacter {
  id: number;
  name: string;
  avatar_url: string | null;
}

export interface Post {
  id: number;
  user_id: number;
  character_id: number | null;
  character?: PostCharacter | null;
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
  author_name?: string | null;
  author_avatar_url?: string | null;
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

  /**
   * Посты журнала конкретной гильдии.
   * Бэкенд: GET /guilds/{guild}/posts
   */
  async getGuildPosts(guildId: number): Promise<Post[]> {
    const res = await http.fetchGet<PostsListResponse | Post[]>(`/guilds/${guildId}/posts`);
    throwOnError(res, 'Ошибка загрузки журнала гильдии');
    const data = res.data;
    if (data && typeof data === 'object' && 'data' in data && Array.isArray((data as PostsListResponse).data)) {
      return (data as PostsListResponse).data;
    }
    return Array.isArray(data) ? data : [];
  },

  /**
   * Посты гильдии, ожидающие публикации (модерация).
   * Бэкенд: GET /guilds/{guild}/posts/pending
   */
  async getGuildPendingPosts(guildId: number): Promise<Post[]> {
    const res = await http.fetchGet<PostsListResponse | Post[]>(`/guilds/${guildId}/posts/pending`);
    throwOnError(res, 'Ошибка загрузки постов на модерации');
    const data = res.data;
    if (data && typeof data === 'object' && 'data' in data && Array.isArray((data as PostsListResponse).data)) {
      return (data as PostsListResponse).data;
    }
    return Array.isArray(data) ? data : [];
  },

  /**
   * Один пост гильдии для просмотра.
   * Бэкенд: GET /guilds/{guild}/posts/{post}
   */
  async getGuildPost(guildId: number, postId: number): Promise<Post> {
    const res = await http.fetchGet<PostResponse | { data: Post } | Post>(`/guilds/${guildId}/posts/${postId}`);
    throwOnError(res, 'Ошибка загрузки поста гильдии');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Публикация поста в гильдии (утверждение модерацией).
   * Бэкенд: POST /guilds/{guild}/posts/{post}/publish
   */
  async publishGuildPost(guildId: number, postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(
      `/guilds/${guildId}/posts/${postId}/publish`,
      {}
    );
    throwOnError(res, 'Ошибка публикации поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Отклонение поста в гильдии.
   * Бэкенд: POST /guilds/{guild}/posts/{post}/reject
   */
  async rejectGuildPost(guildId: number, postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(
      `/guilds/${guildId}/posts/${postId}/reject`,
      {}
    );
    throwOnError(res, 'Ошибка отклонения поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },
};

