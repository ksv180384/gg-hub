/**
 * API персонажей пользователя.
 */

import type { Game, GameClass, Localization, Server } from '@/shared/api/gamesApi';
import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export interface Character {
  id: number;
  user_id: number;
  name: string;
  avatar: string | null;
  avatar_url: string | null;
  game_id: number;
  localization_id: number;
  server_id: number;
  game?: Game;
  localization?: Localization;
  server?: Server;
  game_classes?: GameClass[];
  created_at?: string;
  updated_at?: string;
}

export interface CreateCharacterPayload {
  game_id: number;
  name: string;
  localization_id: number;
  server_id: number;
  avatar?: File | null;
  game_class_ids?: number[];
}

export interface UpdateCharacterPayload {
  name: string;
  localization_id: number;
  server_id: number;
  avatar?: File | null;
  remove_avatar?: boolean;
  game_class_ids?: number[];
}

export interface CharactersListResponse {
  data: Character[];
}

export interface CharacterResponse {
  data: Character;
}

function unwrapData<T>(res: { data: unknown }, fallback: T): T {
  const raw = res.data as { data?: T } | T | null;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data ?? fallback;
  return (raw as T) ?? fallback;
}

export const charactersApi = {
  async getCharacters(gameId?: number): Promise<Character[]> {
    const path = gameId != null ? `/characters?game_id=${gameId}` : '/characters';
    const res = await http.fetchGet<CharactersListResponse | Character[]>(path);
    throwOnError(res, 'Ошибка загрузки персонажей');
    const data = res.data;
    if (Array.isArray((data as CharactersListResponse)?.data)) {
      return (data as CharactersListResponse).data;
    }
    return Array.isArray(data) ? data : [];
  },

  async getCharacter(id: number): Promise<Character> {
    const res = await http.fetchGet<CharacterResponse | Character>(`/characters/${id}`);
    throwOnError(res, 'Ошибка загрузки персонажа');
    return unwrapData(res, {} as Character) as Character;
  },

  async createCharacter(payload: CreateCharacterPayload): Promise<Character> {
    const form = new FormData();
    form.append('game_id', String(payload.game_id));
    form.append('name', payload.name);
    form.append('localization_id', String(payload.localization_id));
    form.append('server_id', String(payload.server_id));
    if (payload.avatar) form.append('avatar', payload.avatar);
    (payload.game_class_ids ?? []).forEach((id) => form.append('game_class_ids[]', String(id)));
    const res = await http.fetchPost<CharacterResponse | Character>('/characters', form);
    throwOnError(res, 'Ошибка создания персонажа');
    return unwrapData(res, {} as Character) as Character;
  },

  async updateCharacter(id: number, payload: UpdateCharacterPayload): Promise<Character> {
    const form = new FormData();
    form.append('_method', 'PUT');
    form.append('name', payload.name);
    form.append('localization_id', String(payload.localization_id));
    form.append('server_id', String(payload.server_id));
    if (payload.remove_avatar) form.append('remove_avatar', '1');
    if (payload.avatar) form.append('avatar', payload.avatar);
    (payload.game_class_ids ?? []).forEach((id) => form.append('game_class_ids[]', String(id)));
    const res = await http.fetchPost<CharacterResponse | Character>(`/characters/${id}`, form);
    throwOnError(res, 'Ошибка обновления персонажа');
    return unwrapData(res, {} as Character) as Character;
  },
};
