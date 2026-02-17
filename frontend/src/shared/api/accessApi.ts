/**
 * API групп прав, прав, ролей и назначения пользователям.
 */

import { http } from '@/shared/api/http';

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

function unwrap<T>(res: { data: T | null; status: number }): T {
  if (res.status >= 400 || !res.data) {
    const d = res.data as { message?: string } | null;
    throw new Error(d?.message ?? 'Ошибка запроса');
  }
  return res.data as T;
}

export const accessApi = {
  async getPermissionGroups(): Promise<PermissionGroupDto[]> {
    const res = await http.fetchGet<{ data: PermissionGroupDto[] }>('/permission-groups');
    const data = unwrap(res) as { data?: PermissionGroupDto[] };
    return data?.data ?? [];
  },

  async createPermissionGroup(payload: { name: string; slug: string }): Promise<PermissionGroupDto> {
    const res = await http.fetchPost<{ data: PermissionGroupDto }>('/permission-groups', payload);
    const data = unwrap(res) as { data?: PermissionGroupDto };
    return data?.data ?? (unwrap(res) as PermissionGroupDto);
  },

  async getPermissions(): Promise<PermissionDto[]> {
    const res = await http.fetchGet<{ data: PermissionDto[] }>('/permissions');
    const data = unwrap(res) as { data?: PermissionDto[] };
    return data?.data ?? [];
  },

  async createPermission(payload: {
    name: string;
    slug: string;
    description?: string;
    permission_group_id: number;
  }): Promise<PermissionDto> {
    const res = await http.fetchPost<{ data: PermissionDto }>('/permissions', payload);
    const data = unwrap(res) as { data?: PermissionDto };
    return data?.data ?? (unwrap(res) as PermissionDto);
  },

  async getRoles(): Promise<RoleDto[]> {
    const res = await http.fetchGet<{ data: RoleDto[] }>('/roles');
    const data = unwrap(res) as { data?: RoleDto[] };
    return data?.data ?? [];
  },

  async getRole(id: number): Promise<RoleDto> {
    const res = await http.fetchGet<{ data: RoleDto }>(`/roles/${id}`);
    const data = unwrap(res) as { data?: RoleDto };
    return data?.data ?? (unwrap(res) as RoleDto);
  },

  async createRole(payload: {
    name: string;
    slug: string;
    description?: string;
    permission_ids?: number[];
  }): Promise<RoleDto> {
    const res = await http.fetchPost<{ data: RoleDto }>('/roles', payload);
    const data = unwrap(res) as { data?: RoleDto };
    return data?.data ?? (unwrap(res) as RoleDto);
  },

  async updateRole(
    id: number,
    payload: { name?: string; slug?: string; description?: string; permission_ids?: number[] }
  ): Promise<RoleDto> {
    const res = await http.fetchPut<{ data: RoleDto }>(`/roles/${id}`, payload);
    const data = unwrap(res) as { data?: RoleDto };
    return data?.data ?? (unwrap(res) as RoleDto);
  },

  async updateUserRolesPermissions(
    userId: number,
    payload: { role_ids?: number[]; permission_ids?: number[] }
  ): Promise<{ permissions: string[]; roles: { id: number; name: string; slug: string }[] }> {
    const res = await http.fetchPut<{ data: { permissions: string[]; roles: { id: number; name: string; slug: string }[] } }>(
      `/users/${userId}/roles-permissions`,
      payload
    );
    const data = unwrap(res) as { data?: { permissions: string[]; roles: { id: number; name: string; slug: string }[] } };
    return data?.data ?? { permissions: [], roles: [] };
  },
};
