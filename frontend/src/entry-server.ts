import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createPinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';
import App from './App.vue';
import { createRouterInstance } from './router';
import { setActiveRouter } from '@/router/activeRouter';
import { useThemeStore } from '@/stores/theme';
import { setupSsrHttpInterceptors } from '@/shared/api/http-interceptors-ssr';
import { computeMainSiteOriginForSsr, mainSiteOriginSsrKey } from '@/shared/lib/mainSiteOriginSsr';
import { ssrRequestContext } from './ssr/requestContext';
import '@/assets/main.css';
import '@cyhnkckali/vue3-color-picker/dist/style.css';
import { buildPageSeoHead, type PageSeoOptions } from '@/shared/lib/usePageSeo';
import { postsApi, type Post } from '@/shared/api/postsApi';

let ssrInterceptorsInstalled = false;

export interface SsrRenderOptions {
  cookie?: string;
  host?: string;
  protocol?: string;
}

export interface SsrRenderResult {
  html: string;
  piniaState: Record<string, unknown>;
  head?: string;
  statusCode?: number;
  /**
   * Если в ходе router.beforeEach произошёл редирект на другой путь — сюда попадает
   * целевой fullPath. Сервер должен отдать HTTP 302, иначе на клиенте случится
   * hydration mismatch (SSR нарисовал одну страницу, клиент пытается нарисовать другую по текущему URL).
   */
  redirect?: string;
}

function stripHtmlToText(input: string): string {
  return input
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function buildPostDescription(post: Post): string {
  const raw = (post.preview ?? post.body ?? '').toString();
  const text = stripHtmlToText(raw);
  if (text.length >= 50) return text.slice(0, 170).trim();
  const base = post.game_name ? `Пост по игре ${post.game_name}.` : 'Пост в gg-hub.';
  return text ? `${text} ${base}`.trim() : base;
}

function buildPostKeywords(post: Post): string {
  const parts = [
    post.title ?? '',
    post.game_name ?? '',
    post.author_name ?? '',
    'gg-hub',
    'журнал',
    'пост',
  ]
    .map((s) => s.trim())
    .filter(Boolean);

  return [...new Set(parts)].join(', ');
}

async function resolveRouteSeo(url: string, origin: string): Promise<PageSeoOptions | undefined> {
  const path = url.split('?')[0] ?? url;
  const match = path.match(/^\/posts\/(\d+)\/?$/);
  if (!match) return undefined;
  const idRaw = match[1];
  if (!idRaw) return undefined;
  const postId = Number(idRaw);
  if (!Number.isFinite(postId)) return undefined;

  const post = await postsApi.getGlobalPost(postId);
  const titleBase = post.title?.trim() || 'Запись';
  const title = `${titleBase} — gg-hub`;
  const canonicalUrl = `${origin}${url}`;
  const description = buildPostDescription(post);

  return {
    title,
    description,
    canonicalUrl,
    ogType: 'article',
    keywords: buildPostKeywords(post),
    jsonLd: {
      '@context': 'https://schema.org',
      '@type': 'Article',
      headline: titleBase,
      description,
      mainEntityOfPage: canonicalUrl,
      datePublished: post.published_at_global ?? post.created_at,
      dateModified: post.updated_at,
      author: post.author_name ? { '@type': 'Person', name: post.author_name } : undefined,
    },
  };
}

/**
 * Рендер приложения на сервере (вызов внутри AsyncLocalStorage с cookie/host).
 */
export async function render(url: string, opts: SsrRenderOptions): Promise<SsrRenderResult> {
  if (!ssrInterceptorsInstalled) {
    setupSsrHttpInterceptors();
    ssrInterceptorsInstalled = true;
  }

  return ssrRequestContext.run(
    { cookie: opts.cookie, host: opts.host, protocol: opts.protocol },
    async () => {
      const pinia = createPinia();
      const router = createRouterInstance(createMemoryHistory(import.meta.env.BASE_URL));
      setActiveRouter(router);

      const app = createSSRApp(App);
      app.provide(mainSiteOriginSsrKey, computeMainSiteOriginForSsr({ host: opts.host, protocol: opts.protocol }));
      app.use(pinia);
      app.use(router);

      const theme = useThemeStore(pinia);
      theme.init();

      await router.push(url);
      await router.isReady();

      const requestedFullPath = router.resolve(url).fullPath;
      const resolvedFullPath = router.currentRoute.value.fullPath;
      if (resolvedFullPath !== requestedFullPath) {
        setActiveRouter(null);
        return { html: '', piniaState: {}, redirect: resolvedFullPath };
      }

      const origin = computeMainSiteOriginForSsr({ host: opts.host, protocol: opts.protocol });
      const routeSeo = await resolveRouteSeo(url, origin).catch(() => undefined);
      const ssrContext: { pageSeo?: PageSeoOptions } = {};
      const html = await renderToString(app, ssrContext);
      const piniaState = pinia.state.value as Record<string, unknown>;
      const pageSeo = ssrContext.pageSeo ?? routeSeo;
      const head = pageSeo ? buildPageSeoHead(pageSeo) : undefined;
      const routeName = router.currentRoute.value.name;
      const statusCode = routeName === 'not-found' || routeName === 'page-not-found' ? 404 : 200;

      setActiveRouter(null);

      return { html, piniaState, head, statusCode };
    },
  );
}
