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
  /** URL аватара (если загружен). */
  avatar_url?: string | null;
  /** Часовой пояс пользователя (например Europe/Moscow). */
  timezone?: string;
  /** Слаги прав доступа (для проверки hasPermission). */
  permissions?: string[];
  /** Роли (isAdmin по slug === 'admin'). */
  roles?: { id: number; name: string; slug: string }[];
}

/** Ответ сервера: логин / регистрация (возвращает user). */
export interface LoginResponse {
  user: User;
}

/** Ответ сервера: регистрация. */
export interface RegisterResponse {
  user: User;
}

/** Ответ сервера: данные текущего пользователя (GET /user). */
export interface UserResponse {
  user: User;
}

/** Ответ сервера: сброс пароля, восстановление и т.п. (только message). */
export interface MessageResponse {
  message?: string;
}

/** Тело запроса: регистрация. */
export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

/** Тело запроса: сброс пароля (по ссылке из письма). */
export interface ResetPasswordPayload {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

/** Тело запроса: смена пароля (текущий + новый). */
export interface UpdatePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

/** Тело запроса: обновление профиля (имя, часовой пояс, аватар). */
export interface UpdateProfilePayload {
  name: string;
  timezone?: string;
  avatar?: File | null;
}

const ROLE_ADMIN_SLUG = 'admin';

/** Слаг права доступа к админ-субдомену и разделам управления (роли, права и т.д.). */
export const PERMISSION_ACCESS_ADMIN = 'admnistrirovanie';

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
      avatar_url: typeof u.avatar_url === 'string' ? u.avatar_url : null,
      timezone: typeof u.timezone === 'string' ? u.timezone : undefined,
      permissions,
      roles,
    };
  }
  return null;
}

export { ROLE_ADMIN_SLUG };

export const authApi = {
  async login(email: string, password: string): Promise<LoginResponse> {
    const res = await http.fetchPost<LoginResponse>('/login', { email, password });
    throwOnError(res, 'Ошибка входа');
    const user = pickUser(res.data);
    if (!user) throw new Error('Сервер не вернул пользователя');
    return { user };
  },

  async register(payload: RegisterPayload): Promise<RegisterResponse> {
    const res = await http.fetchPost<RegisterResponse>('/register', payload as unknown as Record<string, unknown>);
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
    const res = await http.fetchGet<UserResponse>('/user');
    if (res.status >= 400) return null;
    const user = pickUser(res.data) ?? pickUser((res.data as unknown as Record<string, unknown>)?.user);
    return user ?? null;
  },

  async forgotPassword(email: string): Promise<{ message: string }> {
    const res = await http.fetchPost<MessageResponse>('/forgot-password', { email });
    throwOnError(res, 'Ошибка');
    return { message: res.data?.message ?? '' };
  },

  async resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
    const res = await http.fetchPost<MessageResponse>('/reset-password', payload as unknown as Record<string, unknown>);
    throwOnError(res, 'Ошибка');
    return { message: res.data?.message ?? '' };
  },

  async updatePassword(payload: UpdatePasswordPayload): Promise<{ message: string }> {
    const res = await http.fetchPut<MessageResponse>('/user/password', payload as unknown as Record<string, unknown>);
    throwOnError(res, 'Ошибка');
    return { message: res.data?.message ?? '' };
  },

  async updateProfile(payload: UpdateProfilePayload): Promise<{ user: User }> {
    const form = new FormData();
    form.append('name', payload.name);
    if (payload.timezone !== undefined) form.append('timezone', payload.timezone ?? 'UTC');
    if (payload.avatar) {
      form.append('avatar', payload.avatar, payload.avatar.name || 'avatar.jpg');
    }
    const res = await http.fetchPost<UserResponse>('/user', form);
    throwOnError(res, 'Ошибка сохранения профиля');
    const user = pickUser(res.data);
    if (!user) throw new Error('Сервер не вернул пользователя');
    return { user };
  },
};
