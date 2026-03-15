/**
 * API постов пользователя.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface Post {
  id: number;
  title: string | null;
  preview: string | null;
  status_global: string | null;
  status_guild: string | null;
  status_global_label?: string;
  status_guild_label?: string;
  is_visible_global: boolean;
  is_visible_guild: boolean;
  published_at_global: string | null;
  published_at_guild: string | null;
  created_at: string;
  updated_at: string;
  author_name?: string | null;
  author_avatar_url?: string | null;
  views_count?: number;
  comments_count?: number;
  /** Полный текст — только при запросе одного поста. В списках отсутствует. */
  body?: string;
  /** Только для формы редактирования (single post). */
  character_id?: number | null;
  user_id?: number | null;
  guild_id?: number | null;
  game_id?: number | null;
  game_name?: string | null;
  is_anonymous?: boolean;
  is_global_as_guild?: boolean;
  is_hidden?: boolean;
}

export interface PostsListResponse {
  data: Post[];
}

export interface AdminPostsResponse {
  data: Post[];
  meta?: {
    pending_global_count?: number;
    guilds?: { id: number; name: string }[];
    games?: { id: number; name: string }[];
  };
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
  status_global: 'published' | 'draft' | 'hidden';
  status_guild: 'published' | 'draft' | 'hidden';
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
   * Количество постов, ожидающих публикации.
   * Бэкенд: GET /admin/posts-pending-count
   */
  async getAdminPendingCount(): Promise<number> {
    const res = await http.fetchGet<{ count: number }>('/admin/posts-pending-count');
    throwOnError(res, 'Ошибка загрузки');
    return res.data?.count ?? 0;
  },

  /**
   * Все посты для админ-журнала.
   * Бэкенд: GET /admin/posts
   * @param options.filter - "pending_global" — только посты, ожидающие публикации в раздел «Общие»
   * @param options.scope - "global" — общий журнал; "guild" — журналы гильдий
   * @param options.guildId - при scope=guild — фильтр по гильдии
   * @param options.gameId - фильтр по игре
   * @param options.status - при scope=global — по status_global; при scope=guild — по status_guild
   */
  async getAdminPosts(options?: {
    filter?: 'pending_global';
    scope?: 'global' | 'guild';
    guildId?: number;
    gameId?: number;
    status?: string;
  }): Promise<{
    posts: Post[];
    pendingGlobalCount: number;
    guilds: { id: number; name: string }[];
    games: { id: number; name: string }[];
  }> {
    const params = new URLSearchParams();
    if (options?.filter) params.set('filter', options.filter);
    if (options?.scope) params.set('scope', options.scope);
    if (options?.guildId != null && options.scope === 'guild') {
      params.set('guild_id', String(options.guildId));
    }
    if (options?.gameId != null) {
      params.set('game_id', String(options.gameId));
    }
    if (options?.status) {
      params.set('status', options.status);
    }
    const qs = params.toString();
    const url = `/admin/posts${qs ? `?${qs}` : ''}`;
    const res = await http.fetchGet<AdminPostsResponse>(url);
    throwOnError(res, 'Ошибка загрузки журнала');
    const body = res.data;
    const posts =
      body && typeof body === 'object' && 'data' in body && Array.isArray((body as AdminPostsResponse).data)
        ? (body as AdminPostsResponse).data
        : [];
    const pendingGlobalCount = body?.meta?.pending_global_count ?? 0;
    const guilds = body?.meta?.guilds ?? [];
    const games = body?.meta?.games ?? [];
    return { posts, pendingGlobalCount, guilds, games };
  },

  /**
   * Общие посты журнала для игры (раздел «Общие»).
   * Бэкенд: GET /games/{game}/journal-posts
   */
  async getGlobalJournalPosts(gameId: number): Promise<Post[]> {
    const res = await http.fetchGet<PostsListResponse | Post[]>(`/games/${gameId}/journal-posts`);
    throwOnError(res, 'Ошибка загрузки журнала');
    const data = res.data;
    if (data && typeof data === 'object' && 'data' in data && Array.isArray((data as PostsListResponse).data)) {
      return (data as PostsListResponse).data;
    }
    return Array.isArray(data) ? data : [];
  },

  /**
   * Посты журнала конкретной гильдии.
   * Бэкенд: GET /guilds/{guild}/posts
   */
  /**
   * Посты гильдии для журнала. При filter: 'blocked' — только заблокированные (доступно при праве publikovat-post).
   */
  async getGuildPosts(guildId: number, opts?: { filter?: 'blocked' }): Promise<Post[]> {
    const params = opts?.filter ? new URLSearchParams({ filter: opts.filter }) : '';
    const url = params ? `/guilds/${guildId}/posts?${params}` : `/guilds/${guildId}/posts`;
    const res = await http.fetchGet<PostsListResponse | Post[]>(url);
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
   * Засчитать просмотр поста (например, при воспроизведении видео в превью).
   * Бэкенд: POST /guilds/{guild}/posts/{post}/view
   * @returns true, если просмотр засчитан впервые; false, если уже был учтён
   */
  /**
   * Один пост для админки.
   * Бэкенд: GET /admin/posts/{post}
   */
  async getAdminPost(postId: number): Promise<Post> {
    const res = await http.fetchGet<PostResponse | { data: Post } | Post>(`/admin/posts/${postId}`);
    throwOnError(res, 'Ошибка загрузки поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Одобрить пост в гильдии (админка).
   * Бэкенд: POST /admin/posts/{post}/publish
   */
  async publishAdminPost(postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(`/admin/posts/${postId}/publish`, {});
    throwOnError(res, 'Ошибка публикации поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Отклонить пост в гильдии (админка).
   * Бэкенд: POST /admin/posts/{post}/reject
   */
  async rejectAdminPost(postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(`/admin/posts/${postId}/reject`, {});
    throwOnError(res, 'Ошибка отклонения поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Заблокировать пост (общий и гильдия → blocked, автору оповещение).
   * Бэкенд: POST /admin/posts/{post}/block
   */
  async blockAdminPost(postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(`/admin/posts/${postId}/block`, {});
    throwOnError(res, 'Ошибка блокировки поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Скрыть пост (общий и гильдия → hidden, без оповещения автору).
   * Бэкенд: POST /admin/posts/{post}/hide
   */
  async hideAdminPost(postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(`/admin/posts/${postId}/hide`, {});
    throwOnError(res, 'Ошибка скрытия поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Разблокировать пост (админ): в разделах со статусом blocked выставить hidden.
   * Бэкенд: POST /admin/posts/{post}/unblock
   */
  async unblockAdminPost(postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(`/admin/posts/${postId}/unblock`, {});
    throwOnError(res, 'Ошибка разблокировки поста');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  async recordGuildPostView(guildId: number, postId: number): Promise<boolean> {
    const res = await http.fetchPost<{ ok?: boolean; recorded?: boolean }>(
      `/guilds/${guildId}/posts/${postId}/view`,
      {}
    );
    return res.data?.recorded === true;
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

  /**
   * Заблокировать пост только для гильдии (скрыть из гильдейского журнала).
   * Бэкенд: POST /guilds/{guild}/posts/{post}/block
   */
  async blockGuildPost(guildId: number, postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(
      `/guilds/${guildId}/posts/${postId}/block`,
      {}
    );
    throwOnError(res, 'Ошибка блокировки поста в гильдии');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },

  /**
   * Разблокировать пост только для гильдии (status_guild → hidden).
   * Нельзя, если пост заблокирован в общем журнале.
   * Бэкенд: POST /guilds/{guild}/posts/{post}/unblock
   */
  async unblockGuildPost(guildId: number, postId: number): Promise<Post> {
    const res = await http.fetchPost<PostResponse | { data: Post } | Post>(
      `/guilds/${guildId}/posts/${postId}/unblock`,
      {}
    );
    throwOnError(res, 'Ошибка разблокировки поста в гильдии');
    const data = res.data as PostResponse | { data?: Post } | Post | null;
    if (data && typeof data === 'object' && 'data' in data) {
      return (data as { data: Post }).data;
    }
    return data as Post;
  },
};

