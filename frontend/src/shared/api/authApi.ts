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

export interface User {
  id: number;
  name: string;
  email: string;
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

function pickUser(data: unknown): User | null {
  if (!data || typeof data !== 'object') return null;
  const d = data as Record<string, unknown>;
  const candidate = d.user ?? (d.data as Record<string, unknown>)?.user ?? d;
  if (candidate && typeof candidate === 'object' && 'id' in candidate && 'email' in candidate) {
    const u = candidate as User;
    return { id: u.id, name: u.name ?? '', email: u.email };
  }
  return null;
}

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
