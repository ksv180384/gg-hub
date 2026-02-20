/**
 * API контекста сайта (субдомен: admin / игра по слагу).
 * X-Site-Host добавляется в http-interceptors.
 */

import { throwOnError } from '@/shared/api/errors';
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

/** Ответ сервера: контекст сайта (GET /context). */
export interface ContextResponse {
  data: SiteContextData;
}

export const contextApi = {
  async getContext(): Promise<SiteContextData> {
    const res = await http.fetchGet<ContextResponse | SiteContextData>('/context');
    throwOnError(res, 'Нет данных контекста');
    const data = res.data;
    const wrapped = data as ContextResponse;
    return wrapped?.data ?? (data as SiteContextData);
  },
};
