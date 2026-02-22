/**
 * API гильдий.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';
import type { Tag } from '@/shared/api/tagsApi';

export interface GuildGame {
  id: number;
  name: string;
  slug: string;
}

export interface GuildLocalization {
  id: number;
  code: string;
  name: string;
}

export interface GuildServer {
  id: number;
  name: string;
}

export interface GuildLeader {
  id: number;
  name: string;
  server_id: number;
}

/** Гильдия пользователя для меню (текущая игра). */
export interface UserGuildItem {
  id: number;
  name: string;
  is_leader: boolean;
}

export interface Guild {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  logo_path: string | null;
  logo_url: string | null;
  show_roster_to_all: boolean;
  about_text: string | null;
  charter_text: string | null;
  owner_id: number;
  leader_character_id: number | null;
  leader?: GuildLeader | null;
  members_count?: number;
  is_recruiting: boolean;
  game_id: number;
  localization_id: number;
  server_id: number;
  game?: GuildGame;
  localization?: GuildLocalization;
  server?: GuildServer;
  tags?: Tag[];
}

export interface CreateGuildPayload {
  name: string;
  localization_id: number;
  server_id: number;
  leader_character_id: number;
  description?: string;
  tag_ids?: number[];
}

export interface UpdateGuildPayload {
  name?: string;
  localization_id?: number;
  server_id?: number;
  show_roster_to_all?: boolean;
  about_text?: string | null;
  charter_text?: string | null;
  logo?: File | null;
  remove_logo?: boolean;
  tag_ids?: number[];
}

/** Ответ сервера: список гильдий с пагинацией (GET /guilds). */
export interface GuildsListResponse {
  data: Guild[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

/** Ответ сервера при ошибке (message). */
export interface ErrorMessageResponse {
  message?: string;
}

function unwrapGuild(res: { data: unknown }): Guild {
  const raw = res.data as { data?: Guild } | Guild | null;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: Guild }).data!;
  return raw as Guild;
}

export const guildsApi = {
  /** Гильдии текущей игры, в которых состоит пользователь (по персонажам). */
  async getMyGuildsForGame(gameId: number): Promise<UserGuildItem[]> {
    const res = await http.fetchGet<{ data: UserGuildItem[] }>('/user/guilds', {
      params: { game_id: gameId },
    });
    throwOnError(res, 'Ошибка загрузки гильдий');
    const raw = res.data as { data?: UserGuildItem[] } | null;
    return raw?.data ?? [];
  },

  async getGuilds(params?: { per_page?: number; page?: number; game_id?: number; localization_id?: number; server_id?: number }): Promise<{
    guilds: Guild[];
    meta: GuildsListResponse['meta'];
  }> {
    const res = await http.fetchGet<GuildsListResponse>('/guilds', { params });
    throwOnError(res, 'Ошибка загрузки гильдий');
    const data = res.data as GuildsListResponse | null;
    const list = Array.isArray(data?.data) ? data.data : [];
    const meta = data?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 };
    return { guilds: list, meta };
  },

  async getGuild(id: number): Promise<Guild> {
    const res = await http.fetchGet<{ data: Guild } | Guild>(`/guilds/${id}`);
    throwOnError(res, 'Ошибка загрузки гильдии');
    return unwrapGuild(res);
  },

  /**
   * Гильдия для страницы настроек. Только для участников гильдии.
   * При 403 (не состоите в гильдии) нужно перенаправить на /guilds.
   */
  async getGuildForSettings(id: number): Promise<Guild> {
    const res = await http.fetchGet<{ data: Guild } | Guild>(`/guilds/${id}/settings`);
    throwOnError(res, 'Ошибка загрузки гильдии');
    return unwrapGuild(res);
  },

  async createGuild(payload: CreateGuildPayload): Promise<Guild> {
    const res = await http.fetchPost<{ data: Guild } | Guild>('/guilds', {
      name: payload.name,
      localization_id: payload.localization_id,
      server_id: payload.server_id,
      leader_character_id: payload.leader_character_id,
      ...(payload.description != null && payload.description !== '' && { description: payload.description }),
      ...(payload.tag_ids?.length && { tag_ids: payload.tag_ids }),
    });
    throwOnError(res, 'Ошибка создания гильдии');
    return unwrapGuild(res);
  },

  async updateGuild(id: number, payload: UpdateGuildPayload): Promise<Guild> {
    const form = new FormData();
    if (payload.name !== undefined) form.append('name', payload.name);
    if (payload.localization_id !== undefined) form.append('localization_id', String(payload.localization_id));
    if (payload.server_id !== undefined) form.append('server_id', String(payload.server_id));
    if (payload.show_roster_to_all !== undefined) form.append('show_roster_to_all', payload.show_roster_to_all ? '1' : '0');
    if (payload.about_text !== undefined) form.append('about_text', payload.about_text ?? '');
    if (payload.charter_text !== undefined) form.append('charter_text', payload.charter_text ?? '');
    if (payload.remove_logo) form.append('remove_logo', '1');
    if (payload.logo) form.append('logo', payload.logo);
    if (payload.tag_ids !== undefined) {
      payload.tag_ids.forEach((id) => form.append('tag_ids[]', String(id)));
    }
    form.append('_method', 'PUT');
    const res = await http.fetchPost<{ data: Guild } | Guild>(`/guilds/${id}`, form);
    throwOnError(res, 'Ошибка сохранения гильдии');
    return unwrapGuild(res);
  },
};
