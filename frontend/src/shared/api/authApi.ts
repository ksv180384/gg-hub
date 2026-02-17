/**
 * Авторизация (единый http-клиент, base /api/v1 в http.ts).
 */

import { http } from '@/shared/api/http';
import type { HttpResponse } from '@/shared/api/http';

function throwOnError<T>(res: HttpResponse<T>, fallbackMessage: string): asserts res is HttpResponse<T> & { data: T } {
  if (res.status >= 400) {
    const data = res.data as { message?: string; errors?: Record<string, string[]> } | null;
    const err = new Error(data?.message ?? fallbackMessage) as Error & {
      status?: number;
      errors?: Record<string, string[]>;
    };
    err.status = res.status;
    err.errors = data?.errors;
    throw err;
  }
}

/** Пользователь; permissions и roles приходят с api/v1/user (и при логине/регистрации). */
export interface User {
  id: number;
  name: string;
  email: string;
  /** Слаги прав доступа (для проверки hasPermission). */
  permissions?: string[];
  /** Роли (isAdmin по slug === 'admin'). */
  roles?: { id: number; name: string; slug: string }[];
}

export interface LoginResponse {
  user: User;
}

export interface RegisterResponse {
  user: User;
}

export interface UserResponse {
  user: User;
}

const ROLE_ADMIN_SLUG = 'admin';

function pickUser(data: unknown): User | null {
  if (!data || typeof data !== 'object') return null;
  const d = data as Record<string, unknown>;
  const candidate = d.user ?? (d.data as Record<string, unknown>)?.user ?? d;
  if (candidate && typeof candidate === 'object' && 'id' in candidate && 'email' in candidate) {
    const u = candidate as Record<string, unknown>;
    const permissions = Array.isArray(u.permissions) ? (u.permissions as string[]) : undefined;
    const roles = Array.isArray(u.roles) ? (u.roles as { id: number; name: string; slug: string }[]) : undefined;
    return {
      id: u.id as number,
      name: (u.name as string) ?? '',
      email: u.email as string,
      permissions,
      roles,
    };
  }
  return null;
}

export { ROLE_ADMIN_SLUG };

export const authApi = {
  async login(email: string, password: string): Promise<LoginResponse> {
    const res = await http.fetchPost<unknown>('/login', { email, password });
    throwOnError(res, 'Ошибка входа');
    const user = pickUser(res.data);
    if (!user) throw new Error('Сервер не вернул пользователя');
    return { user };
  },

  async register(payload: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<RegisterResponse> {
    const res = await http.fetchPost<unknown>('/register', payload);
    throwOnError(res, 'Ошибка регистрации');
    const user = pickUser(res.data);
    if (!user) throw new Error('Сервер не вернул пользователя');
    return { user };
  },

  async logout(): Promise<void> {
    const res = await http.fetchPost<unknown>('/logout');
    if (res.status >= 400) {
      // всё равно считаем выход выполненным
    }
  },

  async getUser(): Promise<User | null> {
    const res = await http.fetchGet<unknown>('/user');
    if (res.status >= 400) return null;
    const user = pickUser(res.data) ?? pickUser((res.data as Record<string, unknown>)?.user);
    return user ?? null;
  },

  async forgotPassword(email: string): Promise<{ message: string }> {
    const res = await http.fetchPost<{ message?: string }>('/forgot-password', { email });
    throwOnError(res, 'Ошибка');
    return { message: res.data?.message ?? '' };
  },

  async resetPassword(payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    const res = await http.fetchPost<{ message?: string }>('/reset-password', payload);
    throwOnError(res, 'Ошибка');
    return { message: res.data?.message ?? '' };
  },

  async updatePassword(payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    const res = await http.fetchPut<{ message?: string }>('/user/password', payload);
    throwOnError(res, 'Ошибка');
    return { message: res.data?.message ?? '' };
  },
};
