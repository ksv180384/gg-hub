/**
 * Возвращает URL сайта игры по slug (например {slug}.gg-hub.local).
 * Использует VITE_APP_HOST как базовый домен, если задан.
 */
export function getGameSiteUrl(slug: string): string {
  const baseDomain =
    (import.meta.env.VITE_APP_HOST as string | undefined) ||
    (typeof window !== 'undefined' ? getBaseDomainFromHost(window.location.hostname) : 'gg-hub.local');
  const protocol = typeof window !== 'undefined' ? window.location.protocol : 'https:';
  return `${protocol}//${slug}.${baseDomain}`;
}

function getBaseDomainFromHost(hostname: string): string {
  const parts = hostname.split('.');
  if (parts.length >= 3) return parts.slice(1).join('.');
  return hostname;
}
