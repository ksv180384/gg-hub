/**
 * API гильдий.
 */

import axios, { type AxiosError } from 'axios';

const BASE = '/api/v1';

const api = axios.create({
  baseURL: BASE,
  headers: { Accept: 'application/json' },
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined' && window.location?.host) {
    config.headers.set('X-Site-Host', window.location.host);
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    const data = error.response?.data;
    const err = new Error(data?.message || error.message) as Error & {
      status?: number;
      errors?: Record<string, string[]>;
    };
    err.status = error.response?.status;
    err.errors = data?.errors;
    return Promise.reject(err);
  }
);

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
  async getGuilds(params?: { per_page?: number; page?: number }): Promise<{ guilds: Guild[]; meta: GuildsListResponse['meta'] }> {
    const { data } = await api.get<GuildsListResponse>('/guilds', { params });
    const list = Array.isArray((data as GuildsListResponse)?.data) ? (data as GuildsListResponse).data : [];
    const meta = (data as GuildsListResponse)?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 };
    return { guilds: list, meta };
  },
};
