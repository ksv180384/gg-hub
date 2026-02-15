/**
 * API игр и локализаций.
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

export interface Localization {
  id: number;
  code: string;
  name: string;
  is_active: boolean;
}

export interface Game {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  image_preview?: string | null;
  image_thumb?: string | null;
  is_active: boolean;
  localizations?: Localization[];
  created_at?: string;
  updated_at?: string;
}

export interface CreateGamePayload {
  name: string;
  slug: string;
  description?: string;
  image?: File | null;
}

export interface UpdateGamePayload {
  name: string;
  slug: string;
  description?: string;
  image?: File | null;
  remove_image?: boolean;
}

export interface CreateLocalizationPayload {
  code: string;
  name: string;
}

export const gamesApi = {
  async getGames(): Promise<Game[]> {
    const { data } = await api.get<{ data: Game[] }>('/games');
    return Array.isArray(data?.data) ? data.data : (data as unknown as Game[]);
  },

  async getGame(id: number): Promise<Game> {
    const { data } = await api.get<{ data: Game }>(`/games/${id}`);
    return (data as { data?: Game })?.data ?? (data as unknown as Game);
  },

  async createGame(payload: CreateGamePayload): Promise<Game> {
    const form = new FormData();
    form.append('name', payload.name);
    form.append('slug', payload.slug);
    if (payload.description != null) form.append('description', payload.description);
    if (payload.image) form.append('image', payload.image);
    const { data } = await api.post<{ data: Game }>('/games', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return (data as { data?: Game })?.data ?? (data as unknown as Game);
  },

  async updateGame(id: number, payload: UpdateGamePayload): Promise<Game> {
    const form = new FormData();
    form.append('name', payload.name);
    form.append('slug', payload.slug);
    if (payload.description != null) form.append('description', payload.description);
    if (payload.remove_image) form.append('remove_image', '1');
    if (payload.image) form.append('image', payload.image);
    const { data } = await api.post<{ data: Game }>(`/games/${id}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return (data as { data?: Game })?.data ?? (data as unknown as Game);
  },

  async createLocalization(gameId: number, payload: CreateLocalizationPayload): Promise<Localization> {
    const { data } = await api.post<{ data: Localization }>(`/games/${gameId}/localizations`, payload);
    return (data as { data?: Localization })?.data ?? (data as unknown as Localization);
  },

  async deleteGame(gameId: number): Promise<void> {
    await api.delete(`/games/${gameId}`);
  },
};
