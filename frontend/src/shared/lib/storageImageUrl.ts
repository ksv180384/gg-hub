/**
 * Нормализует URL изображения с бэкенда: всегда отдаёт путь относительно текущего origin,
 * чтобы запросы шли на тот же хост (и в dev работал прокси /storage).
 */
export function storageImageUrl(url: string | null | undefined): string {
  if (!url?.trim()) return '';
  if (url.startsWith('/')) return url;
  try {
    const u = new URL(url);
    return u.pathname;
  } catch {
    return url;
  }
}
