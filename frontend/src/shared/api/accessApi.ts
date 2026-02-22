/**
 * API групп прав, прав, ролей и назначения пользователям.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

/** Слаг права: создание и редактирование ролей, прав и категорий прав (сайт). */
export const PERMISSION_MANAGE_ROLES = 'obshhie-roli';

/** Права гильдии: добавление / редактирование / удаление прав гильдии. */
export const PERMISSION_GUILD_ADD = 'dobavliat-pravo-gildii';
export const PERMISSION_GUILD_EDIT = 'redaktirovat-pravo-gildii';
export const PERMISSION_GUILD_DELETE = 'udaliat-pravo-gildii';

export interface PermissionGroupDto {
  id: number;
  scope?: string;
  name: string;
  slug: string;
  permissions?: { id: number; name: string; slug: string; description?: string | null }[];
}

export interface PermissionDto {
  id: number;
  scope?: string;
  name: string;
  slug: string;
  description?: string | null;
  permission_group_id: number;
  group?: { id: number; name: string; slug: string } | null;
}

export interface RoleDto {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  permissions?: { id: number; name: string; slug: string }[];
}

/** Ответ сервера: список групп прав. */
export interface PermissionGroupsResponse {
  data: PermissionGroupDto[];
}

/** Ответ сервера: одна группа прав. */
export interface PermissionGroupResponse {
  data: PermissionGroupDto;
}

/** Ответ сервера: список прав. */
export interface PermissionsResponse {
  data: PermissionDto[];
}

/** Ответ сервера: одно право. */
export interface PermissionResponse {
  data: PermissionDto;
}

/** Ответ сервера: список ролей. */
export interface RolesResponse {
  data: RoleDto[];
}

/** Ответ сервера: одна роль. */
export interface RoleResponse {
  data: RoleDto;
}

/** Ответ сервера: обновление ролей/прав пользователя. */
export interface UserRolesPermissionsResponse {
  data: {
    permissions: string[];
    roles: { id: number; name: string; slug: string }[];
  };
}

/** Пользователь в админке (список / карточка). */
export interface AdminUserDto {
  id: number;
  name: string;
  email: string;
  banned_at: string | null;
  permissions: string[];
  roles: { id: number; name: string; slug: string }[];
}

/** Ответ сервера: список пользователей. */
export interface UsersListResponse {
  data: AdminUserDto[];
}

/** Ответ сервера: один пользователь. */
export interface UserResponse {
  data: AdminUserDto;
}

function unwrapResponse<T>(data: unknown): T {
  const wrapped = data as { data?: T };
  return wrapped?.data ?? (data as T);
}

export const accessApi = {
  async getPermissionGroups(scope?: 'site' | 'guild'): Promise<PermissionGroupDto[]> {
    const url = scope ? `/permission-groups?scope=${scope}` : '/permission-groups';
    const res = await http.fetchGet<PermissionGroupsResponse>(url);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionGroupDto[]>(res.data) ?? [];
  },

  async createPermissionGroup(payload: { scope?: string; name: string; slug: string }): Promise<PermissionGroupDto> {
    const res = await http.fetchPost<PermissionGroupResponse>('/permission-groups', payload);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionGroupDto>(res.data)!;
  },

  async getPermissionGroup(id: number): Promise<PermissionGroupDto> {
    const res = await http.fetchGet<PermissionGroupResponse>(`/permission-groups/${id}`);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionGroupDto>(res.data)!;
  },

  async updatePermissionGroup(
    id: number,
    payload: { name?: string; slug?: string }
  ): Promise<PermissionGroupDto> {
    const res = await http.fetchPut<PermissionGroupResponse>(`/permission-groups/${id}`, payload);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionGroupDto>(res.data)!;
  },

  async deletePermissionGroup(id: number): Promise<void> {
    const res = await http.fetchDelete(`/permission-groups/${id}`);
    throwOnError(res, 'Ошибка удаления');
  },

  async getPermissions(scope?: 'site' | 'guild'): Promise<PermissionDto[]> {
    const url = scope ? `/permissions?scope=${scope}` : '/permissions';
    const res = await http.fetchGet<PermissionsResponse>(url);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionDto[]>(res.data) ?? [];
  },

  async createPermission(payload: {
    name: string;
    slug: string;
    description?: string;
    permission_group_id: number;
  }): Promise<PermissionDto> {
    const res = await http.fetchPost<PermissionResponse>('/permissions', payload);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionDto>(res.data)!;
  },

  async getPermission(id: number): Promise<PermissionDto> {
    const res = await http.fetchGet<PermissionResponse>(`/permissions/${id}`);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionDto>(res.data)!;
  },

  async updatePermission(
    id: number,
    payload: { name?: string; slug?: string; description?: string; permission_group_id?: number }
  ): Promise<PermissionDto> {
    const res = await http.fetchPut<PermissionResponse>(`/permissions/${id}`, payload);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<PermissionDto>(res.data)!;
  },

  async deletePermission(id: number): Promise<void> {
    const res = await http.fetchDelete(`/permissions/${id}`);
    throwOnError(res, 'Ошибка удаления');
  },

  async getRoles(): Promise<RoleDto[]> {
    const res = await http.fetchGet<RolesResponse>('/roles');
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<RoleDto[]>(res.data) ?? [];
  },

  async getRole(id: number): Promise<RoleDto> {
    const res = await http.fetchGet<RoleResponse>(`/roles/${id}`);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<RoleDto>(res.data)!;
  },

  async createRole(payload: {
    name: string;
    slug: string;
    description?: string;
    permission_ids?: number[];
  }): Promise<RoleDto> {
    const res = await http.fetchPost<RoleResponse>('/roles', payload);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<RoleDto>(res.data)!;
  },

  async updateRole(
    id: number,
    payload: { name?: string; slug?: string; description?: string; permission_ids?: number[] }
  ): Promise<RoleDto> {
    const res = await http.fetchPut<RoleResponse>(`/roles/${id}`, payload);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<RoleDto>(res.data)!;
  },

  async updateUserRolesPermissions(
    userId: number,
    payload: { role_ids?: number[]; permission_ids?: number[] }
  ): Promise<{ permissions: string[]; roles: { id: number; name: string; slug: string }[] }> {
    const res = await http.fetchPut<UserRolesPermissionsResponse>(
      `/users/${userId}/roles-permissions`,
      payload as unknown as Record<string, unknown>
    );
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<{ permissions: string[]; roles: { id: number; name: string; slug: string }[] }>(res.data) ?? { permissions: [], roles: [] };
  },

  async getUsers(): Promise<AdminUserDto[]> {
    const res = await http.fetchGet<UsersListResponse>('/users');
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<AdminUserDto[]>(res.data) ?? [];
  },

  async getUser(userId: number): Promise<AdminUserDto> {
    const res = await http.fetchGet<UserResponse>(`/users/${userId}`);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<AdminUserDto>(res.data)!;
  },

  async updateUserBan(userId: number, banned: boolean): Promise<AdminUserDto> {
    const res = await http.fetchPut<UserResponse>(`/users/${userId}`, { banned } as unknown as Record<string, unknown>);
    throwOnError(res, 'Ошибка запроса');
    return unwrapResponse<AdminUserDto>(res.data)!;
  },
};
