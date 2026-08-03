import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type GuildActivityCategory =
  | 'auction'
  | 'roulette'
  | 'storage'
  | 'members'
  | 'access'
  | 'guild'
  | 'journal'
  | 'events';

export type GuildActivityLog = {
  id: number;
  category: GuildActivityCategory;
  action: string;
  description: string;
  subject: {
    type: string | null;
    id: number;
    name: string | null;
  } | null;
  actor: {
    id: number | null;
    name: string | null;
  } | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
};

export type GuildActivityLogListParams = {
  created_from?: string;
  created_to?: string;
  category?: GuildActivityCategory;
  action?: string;
  actor_name?: string;
  search?: string;
  page?: number;
};

export type GuildActivityLogListResult = {
  data: GuildActivityLog[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export const guildActivityApi = {
  async list(
    guildId: number,
    params: GuildActivityLogListParams = {},
  ): Promise<GuildActivityLogListResult> {
    const query = Object.fromEntries(
      Object.entries(params).filter(([, value]) => value !== undefined && value !== ''),
    ) as Record<string, string | number>;

    const response = await http.fetchGet<GuildActivityLogListResult>(
      `/guilds/${guildId}/activity`,
      {
        params: Object.keys(query).length > 0 ? query : undefined,
      },
    );
    throwOnError(response, 'Не удалось загрузить историю гильдии.');

    return response.data ?? {
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 50,
        total: 0,
      },
    };
  },

  async getRouletteSocketToken(guildId: number): Promise<string> {
    const response = await http.fetchPost<{ data: { token: string } }>(
      `/guilds/${guildId}/roulette/socket-token`,
      {},
    );
    throwOnError(response, 'Не удалось подключиться к рулетке.');

    return response.data?.data?.token ?? '';
  },
};
