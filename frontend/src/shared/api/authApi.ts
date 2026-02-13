/**
 * Авторизация через /api/v1 (axios).
 */

import axios, { type AxiosError } from 'axios';

const BASE = '/api/v1';

const api = axios.create({
  baseURL: BASE,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    if (error.response?.status === 401) {
      // if (typeof window !== 'undefined') {
      //   window.location.href = '/login';
      // }
    }
    const data = error.response?.data;
    const err = new Error(data?.message || error.message) as Error & {
      status?: number;
      errors?: Record<string, string[]>;
    };
    err.status = error.response?.status;
    err.errors = data?.errors;
    return Promise.reject(err);
  }
);

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
    const { data } = await api.post<unknown>('/login', { email, password });
    const user = pickUser(data);
    if (!user) throw new Error('Сервер не вернул пользователя');
    return { user };
  },

  async register(payload: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<RegisterResponse> {
    const { data } = await api.post<unknown>('/register', payload);
    const user = pickUser(data);
    if (!user) throw new Error('Сервер не вернул пользователя');
    return { user };
  },

  async logout(): Promise<void> {
    try {
      await api.post('/logout');
    } finally {

    }
  },

  async getUser(): Promise<User | null> {
    try {
      const { data } = await api.get<unknown>('/user');
      const user = pickUser(data) ?? pickUser((data as Record<string, unknown>)?.user);
      return user ?? null;
    } catch {
      return null;
    }
  },

  async forgotPassword(email: string): Promise<{ message: string }> {
    const { data } = await api.post<{ message?: string }>('/forgot-password', { email });
    return { message: data?.message ?? '' };
  },

  async resetPassword(payload: {
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    const { data } = await api.post<{ message?: string }>('/reset-password', payload);
    return { message: data?.message ?? '' };
  },

  async updatePassword(payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ message: string }> {
    const { data } = await api.put<{ message?: string }>('/user/password', payload);
    return { message: data?.message ?? '' };
  },
};
