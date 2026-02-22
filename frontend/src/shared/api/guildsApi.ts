/**
 * API гильдий.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';
import type { PermissionGroupDto } from '@/shared/api/accessApi';
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
  /** Доступ к странице «Роли членов гильдии» (хотя бы одно из прав). */
  can_access_roles?: boolean;
}

export interface Guild {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  logo_path: string | null;
  logo_url: string | null;
  /** URL логотипа 350px для карточек (если есть). */
  logo_card_url?: string | null;
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
  /** Дополнительные поля формы заявки (приходит с GET /guilds/:id/settings). */
  application_form_fields?: GuildApplicationFormFieldDto[];
  /** Права текущего пользователя в гильдии (приходит только с GET /guilds/:id/settings). */
  my_permission_slugs?: string[];
}

/** Дополнительное поле формы заявки гильдии. */
export interface GuildApplicationFormFieldDto {
  id: number;
  guild_id: number;
  name: string;
  type: 'text' | 'textarea' | 'screenshot';
  required: boolean;
  sort_order: number;
}

export interface CreateGuildApplicationFormFieldPayload {
  name: string;
  type: 'text' | 'textarea' | 'screenshot';
  required?: boolean;
}

export interface UpdateGuildApplicationFormFieldPayload {
  name?: string;
  type?: 'text' | 'textarea' | 'screenshot';
  required?: boolean;
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
  is_recruiting?: boolean;
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

/** Роль гильдии (для страницы «Роли членов гильдии»). */
export interface GuildRole {
  id: number;
  guild_id: number;
  name: string;
  slug: string;
  priority: number;
  permissions?: { id: number; name: string; slug: string }[];
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
    if (payload.is_recruiting !== undefined) form.append('is_recruiting', payload.is_recruiting ? '1' : '0');
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

  /** Группы прав гильдии (для страницы ролей). Только для участников гильдии. */
  async getGuildPermissionGroups(guildId: number): Promise<PermissionGroupDto[]> {
    const res = await http.fetchGet<{ data: PermissionGroupDto[] } | PermissionGroupDto[]>(
      `/guilds/${guildId}/permission-groups`
    );
    throwOnError(res, 'Ошибка загрузки прав');
    const raw = res.data as { data?: PermissionGroupDto[] } | PermissionGroupDto[] | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: PermissionGroupDto[] }).data ?? [];
    return Array.isArray(raw) ? raw : [];
  },

  /** Ответ GET /guilds/:id/roles: роли и права текущего пользователя в гильдии. */
  async getGuildRoles(guildId: number): Promise<{ roles: GuildRole[]; myPermissionSlugs: string[] }> {
    const res = await http.fetchGet<{ data: GuildRole[]; my_permission_slugs?: string[] }>(
      `/guilds/${guildId}/roles`
    );
    throwOnError(res, 'Ошибка загрузки ролей');
    const raw = res.data as { data?: GuildRole[]; my_permission_slugs?: string[] } | null;
    const roles = raw && typeof raw === 'object' && 'data' in raw ? (raw.data ?? []) : [];
    const myPermissionSlugs = raw && typeof raw === 'object' && Array.isArray(raw.my_permission_slugs) ? raw.my_permission_slugs : [];
    return { roles, myPermissionSlugs };
  },

  async deleteGuildRole(guildId: number, guildRoleId: number): Promise<void> {
    const res = await http.fetchDelete<{ message?: string }>(`/guilds/${guildId}/roles/${guildRoleId}`);
    throwOnError(res, 'Ошибка удаления роли');
  },

  async createGuildRole(guildId: number, payload: { name: string; slug?: string }): Promise<GuildRole> {
    const res = await http.fetchPost<{ data: GuildRole } | GuildRole>(`/guilds/${guildId}/roles`, payload);
    throwOnError(res, 'Ошибка создания роли');
    const raw = res.data as { data?: GuildRole } | GuildRole | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) return (raw as { data: GuildRole }).data!;
    return raw as GuildRole;
  },

  async updateGuildRolePermissions(guildId: number, guildRoleId: number, permissionIds: number[]): Promise<GuildRole> {
    const res = await http.fetchPut<{ data: GuildRole } | GuildRole>(
      `/guilds/${guildId}/roles/${guildRoleId}/permissions`,
      { permission_ids: permissionIds }
    );
    throwOnError(res, 'Ошибка сохранения прав');
    const raw = res.data as { data?: GuildRole } | GuildRole | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw) return (raw as { data: GuildRole }).data!;
    return raw as GuildRole;
  },

  async createApplicationFormField(
    guildId: number,
    payload: CreateGuildApplicationFormFieldPayload
  ): Promise<GuildApplicationFormFieldDto> {
    const res = await http.fetchPost<{ data: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto>(
      `/guilds/${guildId}/application-form-fields`,
      { name: payload.name, type: payload.type, required: payload.required ?? false }
    );
    throwOnError(res, 'Ошибка добавления поля');
    const raw = res.data as { data?: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationFormFieldDto }).data!;
    return raw as GuildApplicationFormFieldDto;
  },

  async updateApplicationFormField(
    guildId: number,
    fieldId: number,
    payload: UpdateGuildApplicationFormFieldPayload
  ): Promise<GuildApplicationFormFieldDto> {
    const res = await http.fetchPut<{ data: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto>(
      `/guilds/${guildId}/application-form-fields/${fieldId}`,
      payload
    );
    throwOnError(res, 'Ошибка сохранения поля');
    const raw = res.data as { data?: GuildApplicationFormFieldDto } | GuildApplicationFormFieldDto | null;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'data' in raw)
      return (raw as { data: GuildApplicationFormFieldDto }).data!;
    return raw as GuildApplicationFormFieldDto;
  },

  async deleteApplicationFormField(guildId: number, fieldId: number): Promise<void> {
    const res = await http.fetchDelete(`/guilds/${guildId}/application-form-fields/${fieldId}`);
    throwOnError(res, 'Ошибка удаления поля');
  },
};
