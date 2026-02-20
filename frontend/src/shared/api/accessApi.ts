/**
 * API групп прав, прав, ролей и назначения пользователям.
 */

import { http } from '@/shared/api/http';

/** Слаг права: создание и редактирование ролей, прав и категорий прав. */
export const PERMISSION_MANAGE_ROLES = 'obshhie-roli';

export interface PermissionGroupDto {
  id: number;
  name: string;
  slug: string;
  permissions?: { id: number; name: string; slug: string; description?: string | null }[];
}

export interface PermissionDto {
  id: number;
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

function unwrap<T>(res: { data: T | null; status: number }): T {
  if (res.status >= 400 || !res.data) {
    const d = res.data as { message?: string } | null;
    throw new Error(d?.message ?? 'Ошибка запроса');
  }
  return res.data as T;
}

export const accessApi = {
  async getPermissionGroups(): Promise<PermissionGroupDto[]> {
    const res = await http.fetchGet<PermissionGroupsResponse>('/permission-groups');
    const data = unwrap(res) as PermissionGroupsResponse;
    return data?.data ?? [];
  },

  async createPermissionGroup(payload: { name: string; slug: string }): Promise<PermissionGroupDto> {
    const res = await http.fetchPost<PermissionGroupResponse>('/permission-groups', payload);
    const data = unwrap(res) as PermissionGroupResponse;
    return data?.data ?? (unwrap(res) as unknown as PermissionGroupDto);
  },

  async getPermissions(): Promise<PermissionDto[]> {
    const res = await http.fetchGet<PermissionsResponse>('/permissions');
    const data = unwrap(res) as PermissionsResponse;
    return data?.data ?? [];
  },

  async createPermission(payload: {
    name: string;
    slug: string;
    description?: string;
    permission_group_id: number;
  }): Promise<PermissionDto> {
    const res = await http.fetchPost<PermissionResponse>('/permissions', payload);
    const data = unwrap(res) as PermissionResponse;
    return data?.data ?? (unwrap(res) as unknown as PermissionDto);
  },

  async getRoles(): Promise<RoleDto[]> {
    const res = await http.fetchGet<RolesResponse>('/roles');
    const data = unwrap(res) as RolesResponse;
    return data?.data ?? [];
  },

  async getRole(id: number): Promise<RoleDto> {
    const res = await http.fetchGet<RoleResponse>(`/roles/${id}`);
    const data = unwrap(res) as RoleResponse;
    return data?.data ?? (unwrap(res) as unknown as RoleDto);
  },

  async createRole(payload: {
    name: string;
    slug: string;
    description?: string;
    permission_ids?: number[];
  }): Promise<RoleDto> {
    const res = await http.fetchPost<RoleResponse>('/roles', payload);
    const data = unwrap(res) as RoleResponse;
    return data?.data ?? (unwrap(res) as unknown as RoleDto);
  },

  async updateRole(
    id: number,
    payload: { name?: string; slug?: string; description?: string; permission_ids?: number[] }
  ): Promise<RoleDto> {
    const res = await http.fetchPut<RoleResponse>(`/roles/${id}`, payload);
    const data = unwrap(res) as RoleResponse;
    return data?.data ?? (unwrap(res) as unknown as RoleDto);
  },

  async updateUserRolesPermissions(
    userId: number,
    payload: { role_ids?: number[]; permission_ids?: number[] }
  ): Promise<{ permissions: string[]; roles: { id: number; name: string; slug: string }[] }> {
    const res = await http.fetchPut<UserRolesPermissionsResponse>(
      `/users/${userId}/roles-permissions`,
      payload as unknown as Record<string, unknown>
    );
    const data = unwrap(res) as UserRolesPermissionsResponse;
    return data?.data ?? { permissions: [], roles: [] };
  },

  async getUsers(): Promise<AdminUserDto[]> {
    const res = await http.fetchGet<UsersListResponse>('/users');
    const data = unwrap(res) as UsersListResponse;
    return data?.data ?? [];
  },

  async getUser(userId: number): Promise<AdminUserDto> {
    const res = await http.fetchGet<UserResponse>(`/users/${userId}`);
    const data = unwrap(res) as UserResponse;
    return data?.data ?? (unwrap(res) as unknown as AdminUserDto);
  },

  async updateUserBan(userId: number, banned: boolean): Promise<AdminUserDto> {
    const res = await http.fetchPut<UserResponse>(`/users/${userId}`, { banned } as unknown as Record<string, unknown>);
    const data = unwrap(res) as UserResponse;
    return data?.data ?? (unwrap(res) as unknown as AdminUserDto);
  },
};
