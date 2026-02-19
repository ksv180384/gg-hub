/**
 * API игр и локализаций.
 */

import { http } from '@/shared/api/http';

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

/** Ответ сервера: список игр (GET /games). */
export interface GamesListResponse {
  data: Game[];
}

/** Ответ сервера: одна игра (GET/POST/PUT /games, /games/:id). */
export interface GameResponse {
  data: Game;
}

/** Ответ сервера: локализация (POST /games/:id/localizations). */
export interface LocalizationResponse {
  data: Localization;
}

function unwrapData<T>(res: { data: T | null; status: number }, fallback: T): T {
  if (res.status >= 400) {
    const d = res.data as { message?: string; errors?: Record<string, string[]> } | null;
    const err = new Error(d?.message ?? 'Ошибка') as Error & {
      status?: number;
      errors?: Record<string, string[]>;
    };
    err.status = res.status;
    err.errors = d?.errors;
    throw err;
  }
  const raw = res.data as { data?: T } | T | null;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data ?? fallback;
  return (raw as T) ?? fallback;
}

export const gamesApi = {
  async getGames(): Promise<Game[]> {
    const res = await http.fetchGet<GamesListResponse | Game[]>('/games');
    if (res.status >= 400) {
      const d = res.data as { message?: string } | null;
      throw new Error(d?.message ?? 'Ошибка загрузки игр');
    }
    const data = res.data;
    return Array.isArray((data as GamesListResponse)?.data)
      ? (data as GamesListResponse).data
      : (Array.isArray(data) ? data : []);
  },

  async getGame(id: number): Promise<Game> {
    const res = await http.fetchGet<GameResponse | Game>(`/games/${id}`);
    return unwrapData(res, {} as Game) as Game;
  },

  async createGame(payload: CreateGamePayload): Promise<Game> {
    const form = new FormData();
    form.append('name', payload.name);
    form.append('slug', payload.slug);
    if (payload.description != null) form.append('description', payload.description);
    if (payload.image) form.append('image', payload.image);
    const res = await http.fetchPost<GameResponse | Game>('/games', form);
    return unwrapData(res, {} as Game) as Game;
  },

  async updateGame(id: number, payload: UpdateGamePayload): Promise<Game> {
    const form = new FormData();
    form.append('name', payload.name);
    form.append('slug', payload.slug);
    if (payload.description != null) form.append('description', payload.description);
    if (payload.remove_image) form.append('remove_image', '1');
    if (payload.image) form.append('image', payload.image);
    const res = await http.fetchPost<GameResponse | Game>(`/games/${id}`, form);
    return unwrapData(res, {} as Game) as Game;
  },

  async createLocalization(gameId: number, payload: CreateLocalizationPayload): Promise<Localization> {
    const res = await http.fetchPost<LocalizationResponse | Localization>(
      `/games/${gameId}/localizations`,
      payload as unknown as Record<string, unknown>
    );
    return unwrapData(res, {} as Localization) as Localization;
  },

  async deleteGame(gameId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(`/games/${gameId}`);
    if (res.status >= 400) {
      const d = res.data as { message?: string } | null;
      throw new Error(d?.message ?? 'Ошибка удаления');
    }
  },
};
