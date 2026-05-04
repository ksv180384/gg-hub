import type { InjectionKey } from 'vue';
import { DEFAULT_PRODUCTION_ORIGIN } from '@/seo/homePageSeo';

/** Значение из HTTP-запроса SSR — см. entry-server `app.provide`. В браузере не задаётся. */
export const mainSiteOriginSsrKey: InjectionKey<string> = Symbol('mainSiteOriginSsr');

function baseHostStripGameSubdomain(hostname: string): string {
  const parts = hostname.split('.');
  return parts.length >= 3 ? parts.slice(1).join('.') : hostname;
}

/** Origin «основного» сайта для SSR HTML (совпадает с клиентским расчётом по window без VITE_SITE_URL). */
export function computeMainSiteOriginForSsr(opts: { host?: string; protocol?: string }): string {
  const fromEnv = import.meta.env.VITE_SITE_URL as string | undefined;
  if (fromEnv && /^https?:\/\//i.test(fromEnv.trim())) {
    return fromEnv.trim().replace(/\/$/, '');
  }
  if (opts.host) {
    const hostname = opts.host.split(':')[0];
    const proto = opts.protocol === 'https' ? 'https' : 'http';
    return `${proto}://${baseHostStripGameSubdomain(hostname)}`;
  }
  return DEFAULT_PRODUCTION_ORIGIN;
}

/** Общий расчёт origin «основного» сайта для клиентских компонентов. */
export function getMainSiteOrigin(mainSiteOriginFromSsr?: string): string {
  const fromEnv = import.meta.env.VITE_SITE_URL as string | undefined;
  if (fromEnv && /^https?:\/\//i.test(fromEnv.trim())) {
    return fromEnv.trim().replace(/\/$/, '');
  }
  if (typeof window === 'undefined') {
    if (mainSiteOriginFromSsr) return mainSiteOriginFromSsr;
    return DEFAULT_PRODUCTION_ORIGIN;
  }
  const { protocol, hostname } = window.location;
  return `${protocol}//${baseHostStripGameSubdomain(hostname)}`;
}

