/**
 * API игр и локализаций.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface Server {
  id: number;
  name: string;
  slug: string;
  is_active: boolean;
}

export interface Localization {
  id: number;
  code: string;
  name: string;
  is_active: boolean;
  servers?: Server[];
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

/** Извлекает data из обёртки { data } или возвращает сам ответ. */
function unwrapData<T>(res: { data: unknown }, fallback: T): T {
  const raw = res.data as { data?: T } | T | null;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data ?? fallback;
  return (raw as T) ?? fallback;
}

export const gamesApi = {
  async getGames(): Promise<Game[]> {
    const res = await http.fetchGet<GamesListResponse | Game[]>('/games');
    throwOnError(res, 'Ошибка загрузки игр');
    const data = res.data;
    return Array.isArray((data as GamesListResponse)?.data)
      ? (data as GamesListResponse).data
      : (Array.isArray(data) ? data : []);
  },

  async getGame(id: number): Promise<Game> {
    const res = await http.fetchGet<GameResponse | Game>(`/games/${id}`);
    throwOnError(res, 'Ошибка загрузки игры');
    return unwrapData(res, {} as Game) as Game;
  },

  async createGame(payload: CreateGamePayload): Promise<Game> {
    const form = new FormData();
    form.append('name', payload.name);
    form.append('slug', payload.slug);
    if (payload.description != null) form.append('description', payload.description);
    if (payload.image) form.append('image', payload.image);
    const res = await http.fetchPost<GameResponse | Game>('/games', form);
    throwOnError(res, 'Ошибка создания игры');
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
    throwOnError(res, 'Ошибка обновления игры');
    return unwrapData(res, {} as Game) as Game;
  },

  async createLocalization(gameId: number, payload: CreateLocalizationPayload): Promise<Localization> {
    const res = await http.fetchPost<LocalizationResponse | Localization>(
      `/games/${gameId}/localizations`,
      payload as unknown as Record<string, unknown>
    );
    throwOnError(res, 'Ошибка создания локализации');
    return unwrapData(res, {} as Localization) as Localization;
  },

  async deleteGame(gameId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(`/games/${gameId}`);
    throwOnError(res, 'Ошибка удаления');
  },

  // Сервера локализации
  async getServers(gameId: number, localizationId: number): Promise<Server[]> {
    const res = await http.fetchGet<{ data: Server[] }>(
      `/games/${gameId}/localizations/${localizationId}/servers`
    );
    throwOnError(res, 'Ошибка загрузки серверов');
    const raw = res.data as { data?: Server[] } | null;
    return raw?.data ?? [];
  },

  async createServer(
    gameId: number,
    localizationId: number,
    payload: { name: string; slug: string; is_active?: boolean }
  ): Promise<Server> {
    const res = await http.fetchPost<{ data: Server } | Server>(
      `/games/${gameId}/localizations/${localizationId}/servers`,
      payload as unknown as Record<string, unknown>
    );
    throwOnError(res, 'Ошибка добавления сервера');
    return unwrapData(res, {} as Server) as Server;
  },

  async updateServer(
    serverId: number,
    payload: { name?: string; slug?: string; is_active?: boolean }
  ): Promise<Server> {
    const res = await http.fetchPut<{ data: Server } | Server>(`/servers/${serverId}`, payload);
    throwOnError(res, 'Ошибка обновления сервера');
    return unwrapData(res, {} as Server) as Server;
  },

  async deleteServer(serverId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(`/servers/${serverId}`);
    throwOnError(res, 'Ошибка удаления сервера');
  },

  /** Объединить несколько серверов в один (персонажи и гильдии переносятся на целевой сервер). */
  async mergeServers(
    gameId: number,
    localizationId: number,
    payload: { target_server_id: number; source_server_ids: number[] }
  ): Promise<{ message: string; target_server_id: number }> {
    const res = await http.fetchPost<{ message: string; target_server_id: number }>(
      `/games/${gameId}/localizations/${localizationId}/servers/merge`,
      payload as unknown as Record<string, unknown>
    );
    throwOnError(res, 'Ошибка объединения');
    return res.data as { message: string; target_server_id: number };
  },
};
