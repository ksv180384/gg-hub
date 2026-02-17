/**
 * API контекста сайта (субдомен: admin / игра по слагу).
 * X-Site-Host добавляется в http-interceptors.
 */

import { http } from '@/shared/api/http';

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
    const res = await http.fetchGet<{ data: SiteContextData } | SiteContextData>('/context');
    const data = res.data;
    if (!data) throw new Error('Нет данных контекста');
    const wrapped = data as { data?: SiteContextData };
    return wrapped?.data ?? (data as SiteContextData);
  },
};
