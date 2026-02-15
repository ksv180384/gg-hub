/**
 * API контекста сайта (субдомен: admin / игра по слагу).
 */

import axios, { type AxiosError } from 'axios';

const BASE = '/api/v1';

const api = axios.create({
  baseURL: BASE,
  headers: { Accept: 'application/json' },
  withCredentials: true,
});

// Чтобы бэкенд определял контекст по субдомену при прокси (dev)
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined' && window.location?.host) {
    config.headers.set('X-Site-Host', window.location.host);
  }
  return config;
});

export interface GameContext {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  is_active: boolean;
}

export interface SiteContextData {
  mode: 'admin' | 'game' | 'main';
  subdomain: string | null;
  game: GameContext | null;
}

export const contextApi = {
  async getContext(): Promise<SiteContextData> {
    const { data } = await api.get<{ data: SiteContextData }>('/context');
    return (data as { data?: SiteContextData })?.data ?? (data as unknown as SiteContextData);
  },
};
