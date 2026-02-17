/**
 * API гильдий.
 */

import { http } from '@/shared/api/http';

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

export interface Guild {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  owner_id: number;
  is_recruiting: boolean;
  game_id: number;
  localization_id: number;
  server_id: number;
  game?: GuildGame;
  localization?: GuildLocalization;
  server?: GuildServer;
}

export interface GuildsListResponse {
  data: Guild[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export const guildsApi = {
  async getGuilds(params?: { per_page?: number; page?: number }): Promise<{
    guilds: Guild[];
    meta: GuildsListResponse['meta'];
  }> {
    const res = await http.fetchGet<GuildsListResponse>('/guilds', { params });
    if (res.status >= 400) {
      const d = res.data as { message?: string } | null;
      throw new Error(d?.message ?? 'Ошибка загрузки гильдий');
    }
    const data = res.data as GuildsListResponse | null;
    const list = Array.isArray(data?.data) ? data.data : [];
    const meta = data?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 };
    return { guilds: list, meta };
  },
};
