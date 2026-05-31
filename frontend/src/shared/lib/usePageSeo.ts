import { onMounted, onUnmounted } from 'vue';
import { DEFAULT_PRODUCTION_ORIGIN } from '@/seo/homePageSeo';

const SEO_MARK = 'data-gg-seo';

export interface PageSeoOptions {
  title: string;
  description: string;
  canonicalUrl: string;
  ogImageUrl?: string;
  ogType?: 'website' | 'article';
  keywords?: string;
  jsonLd?: Record<string, unknown> | Record<string, unknown>[];
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function escapeScriptJson(value: unknown): string {
  return JSON.stringify(value).replace(/</g, '\\u003c');
}

export function buildPageSeoHead(options: PageSeoOptions): string {
  const tags: string[] = [];
  const meta = (attr: 'name' | 'property', key: string, content: string) => {
    tags.push(
      `<meta ${attr}="${escapeHtml(key)}" content="${escapeHtml(content)}">`
    );
  };

  tags.push(`<title>${escapeHtml(options.title)}</title>`);
  meta('name', 'description', options.description);
  if (options.keywords) {
    meta('name', 'keywords', options.keywords);
  }
  meta('name', 'robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
  meta('name', 'author', 'gg-hub');
  meta('property', 'og:type', options.ogType ?? 'website');
  meta('property', 'og:locale', 'ru_RU');
  meta('property', 'og:url', options.canonicalUrl);
  meta('property', 'og:title', options.title);
  meta('property', 'og:description', options.description);
  meta('property', 'og:site_name', 'gg-hub');
  if (options.ogImageUrl) {
    meta('property', 'og:image', options.ogImageUrl);
    meta('property', 'og:image:alt', options.title);
  }
  meta('name', 'twitter:card', options.ogImageUrl ? 'summary_large_image' : 'summary');
  meta('name', 'twitter:title', options.title);
  meta('name', 'twitter:description', options.description);
  if (options.ogImageUrl) {
    meta('name', 'twitter:image', options.ogImageUrl);
  }
  tags.push(`<link rel="canonical" href="${escapeHtml(options.canonicalUrl)}">`);

  if (options.jsonLd) {
    const graph = Array.isArray(options.jsonLd) ? options.jsonLd : [options.jsonLd];
    const payload = graph.length > 1 ? { '@context': 'https://schema.org', '@graph': graph } : graph[0]!;
    tags.push(
      `<script type="application/ld+json" id="gg-hub-ld-json">${escapeScriptJson(payload)}</script>`
    );
  }

  return tags.join('\n    ');
}

/**
 * Немедленно применяет SEO к текущей странице и возвращает cleanup-функцию.
 * Полезно для страниц, где данные (title/description) появляются после загрузки API.
 */
export function applyPageSeo(options: PageSeoOptions): () => void {
  const undo: (() => void)[] = [];

  function setMeta(attr: 'name' | 'property', key: string, content: string) {
    let el = document.querySelector(`meta[${attr}="${key}"]`) as HTMLMetaElement | null;
    if (!el) {
      el = document.createElement('meta');
      el.setAttribute(attr, key);
      el.setAttribute(SEO_MARK, '');
      el.setAttribute('content', content);
      document.head.appendChild(el);
      undo.push(() => el!.remove());
    } else {
      const prev = el.getAttribute('content');
      el.setAttribute('content', content);
      el.setAttribute(SEO_MARK, '');
      undo.push(() => {
        if (prev == null) {
          el!.removeAttribute('content');
        } else {
          el!.setAttribute('content', prev);
        }
        el!.removeAttribute(SEO_MARK);
      });
    }
  }

  function setCanonical(href: string) {
    const existing = document.querySelector('link[rel="canonical"]') as HTMLLinkElement | null;
    if (!existing) {
      const el = document.createElement('link');
      el.rel = 'canonical';
      el.href = href;
      el.setAttribute(SEO_MARK, '');
      document.head.appendChild(el);
      undo.push(() => el.remove());
      return;
    }
    const prev = existing.getAttribute('href');
    existing.href = href;
    existing.setAttribute(SEO_MARK, '');
    undo.push(() => {
      if (prev == null) {
        existing.removeAttribute('href');
      } else {
        existing.setAttribute('href', prev);
      }
      existing.removeAttribute(SEO_MARK);
    });
  }

  function setJsonLd(json: Record<string, unknown>) {
    const id = 'gg-hub-ld-json';
    document.querySelectorAll(`script#${id}`).forEach((n) => n.remove());
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.id = id;
    script.setAttribute(SEO_MARK, '');
    script.textContent = JSON.stringify(json);
    document.head.appendChild(script);
    undo.push(() => script.remove());
  }

  const prevTitle = document.title;
  document.title = options.title;
  undo.push(() => {
    document.title = prevTitle;
  });

  setMeta('name', 'description', options.description);
  if (options.keywords) {
    setMeta('name', 'keywords', options.keywords);
  }

  setMeta('name', 'robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
  setMeta('name', 'author', 'gg-hub');

  setMeta('property', 'og:type', options.ogType ?? 'website');
  setMeta('property', 'og:locale', 'ru_RU');
  setMeta('property', 'og:url', options.canonicalUrl);
  setMeta('property', 'og:title', options.title);
  setMeta('property', 'og:description', options.description);
  setMeta('property', 'og:site_name', 'gg-hub');
  if (options.ogImageUrl) {
    setMeta('property', 'og:image', options.ogImageUrl);
    setMeta('property', 'og:image:alt', options.title);
  }

  setMeta('name', 'twitter:card', options.ogImageUrl ? 'summary_large_image' : 'summary');
  setMeta('name', 'twitter:title', options.title);
  setMeta('name', 'twitter:description', options.description);
  if (options.ogImageUrl) {
    setMeta('name', 'twitter:image', options.ogImageUrl);
  }

  setCanonical(options.canonicalUrl);

  if (options.jsonLd) {
    const graph = Array.isArray(options.jsonLd) ? options.jsonLd : [options.jsonLd];
    const payload = graph.length > 1 ? { '@context': 'https://schema.org', '@graph': graph } : graph[0]!;
    setJsonLd(payload as Record<string, unknown>);
  }

  return () => {
    while (undo.length) {
      const fn = undo.pop();
      fn?.();
    }
  };
}

/**
 * Устанавливает title, meta, canonical и JSON-LD для SPA-страницы.
 * При размонтировании восстанавливает предыдущие значения document.title и meta.
 */
export function usePageSeo(options: PageSeoOptions) {
  let cleanup: (() => void) | null = null;

  onMounted(() => {
    cleanup = applyPageSeo(options);
  });

  onUnmounted(() => {
    cleanup?.();
    cleanup = null;
  });
}

/** Базовый origin сайта для канонических URL и OG (без завершающего слэша). */
export function getSiteOrigin(): string {
  if (typeof window !== 'undefined') {
    const protocol = window.location.hostname === 'gg-hub.local' || window.location.hostname.endsWith('.gg-hub.local')
      ? 'http:'
      : window.location.protocol;
    return `${protocol}//${window.location.host}`;
  }
  const fromEnv = import.meta.env.VITE_SITE_URL as string | undefined;
  if (fromEnv && /^https?:\/\//i.test(fromEnv)) {
    return fromEnv.replace(/\/$/, '');
  }
  return DEFAULT_PRODUCTION_ORIGIN;
}
