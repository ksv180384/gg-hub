import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type LandingCtaButton = 'start_free' | 'create_account';

export interface LandingCtaClickStats {
  total: number;
  start_free: number;
  create_account: number;
  last_click_at: string | null;
}

export async function recordLandingCtaClick(button: LandingCtaButton): Promise<boolean> {
  const { status } = await http.fetchPost<unknown>('/landing/cta-clicks', { button });
  return status === 201;
}

/** GET /admin/landing-cta-clicks/stats (требуется админ-доступ). */
export async function getLandingCtaClickStats(): Promise<LandingCtaClickStats> {
  const res = await http.fetchGet<{ data: LandingCtaClickStats }>('/admin/landing-cta-clicks/stats');
  throwOnError(res, 'Не удалось загрузить статистику кликов');
  const payload = res.data?.data;
  if (payload == null) {
    throw new Error('Пустой ответ статистики');
  }
  return payload;
}
