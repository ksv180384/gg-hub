/**
 * Нормализует URL изображения с бэкенда: всегда отдаёт путь относительно текущего origin,
 * чтобы запросы шли на тот же хост (и в dev работал прокси /storage).
 * Query-параметры (например ?v= для обхода кэша) сохраняются.
 */
export function storageImageUrl(url: string | null | undefined): string {
  if (!url?.trim()) return '';
  if (url.startsWith('/')) return url;
  try {
    const u = new URL(url);
    const path = u.pathname + u.search;
    return path;
  } catch {
    return url;
  }
}
