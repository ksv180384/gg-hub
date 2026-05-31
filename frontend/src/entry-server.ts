import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createPinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';
import App from './App.vue';
import { createRouterInstance } from './router';
import { setActiveRouter } from '@/router/activeRouter';
import { useThemeStore } from '@/stores/theme';
import { setupSsrHttpInterceptors } from '@/shared/api/http-interceptors-ssr';
import {
  computeMainSiteOriginForSsr,
  computeRequestOriginForSsr,
  mainSiteOriginSsrKey,
} from '@/shared/lib/mainSiteOriginSsr';
import { ssrRequestContext } from './ssr/requestContext';
import '@/assets/main.css';
import '@cyhnkckali/vue3-color-picker/dist/style.css';
import { buildPageSeoHead, type PageSeoOptions } from '@/shared/lib/usePageSeo';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { guildsApi, type Guild, type GuildApplicationFormData } from '@/shared/api/guildsApi';
import { gamesApi, type GameCatalogItem } from '@/shared/api/gamesApi';
import { useSiteContextStore } from '@/stores/siteContext';
import { useSsrPageDataStore } from '@/stores/ssrPageData';

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

function truncateSeoText(text: string, maxLen = 160): string {
  const normalized = text.replace(/\s+/g, ' ').trim();
  if (normalized.length <= maxLen) return normalized;
  return `${normalized.slice(0, Math.max(0, maxLen - 1)).trimEnd()}…`;
}

function buildGameOriginForSsr(origin: string, gameSlug?: string | null): string {
  const slug = gameSlug?.trim();
  if (!slug) return origin;

  try {
    const url = new URL(origin);
    const baseDomain = ['gg-hub.local', 'gg-hub.ru'].find(
      (domain) => url.hostname === domain || url.hostname.endsWith(`.${domain}`),
    );
    if (!baseDomain) return origin;

    const port = url.port ? `:${url.port}` : '';
    return `${url.protocol}//${slug}.${baseDomain}${port}`;
  } catch {
    return origin;
  }
}

function buildGuildDescription(guild: Guild): string {
  const aboutText = guild.about_text ? stripHtmlToText(guild.about_text) : '';
  const gameName = guild.game?.name?.trim() ?? '';
  const serverName = guild.server?.name?.trim() ?? '';
  const tagText = (guild.tags ?? []).map((tag) => tag.name).filter(Boolean).slice(0, 8).join(', ');
  const parts = [
    aboutText,
    gameName ? `Игра: ${gameName}.` : '',
    serverName ? `Сервер: ${serverName}.` : '',
    tagText ? `Теги: ${tagText}.` : '',
    `Профиль гильдии «${guild.name}» на gg-hub.`,
  ].filter(Boolean);

  return truncateSeoText(parts.join(' '));
}

function buildGuildKeywords(guild: Guild): string {
  const gameName = guild.game?.name?.trim() ?? '';
  const serverName = guild.server?.name?.trim() ?? '';
  const parts = [
    guild.name,
    gameName ? `гильдия ${gameName}` : 'гильдия',
    serverName ? `сервер ${serverName}` : '',
    ...(guild.tags ?? []).map((tag) => tag.name),
    'каталог гильдий',
    'gg-hub',
  ]
    .map((value) => value.trim())
    .filter(Boolean);

  return [...new Set(parts)].slice(0, 18).join(', ');
}

function buildGuildInfoSeo(guild: Guild, origin: string): PageSeoOptions {
  const gameName = guild.game?.name?.trim() ?? '';
  const serverName = guild.server?.name?.trim() ?? '';
  const titleBase = gameName ? `${guild.name} — гильдия ${gameName}` : `${guild.name} — гильдия`;
  const guildOrigin = buildGameOriginForSsr(origin, guild.game?.slug);
  const canonicalUrl = `${guildOrigin}/guilds/${guild.id}/info`;

  return {
    title: `${titleBase} — gg-hub`,
    description: buildGuildDescription(guild),
    keywords: buildGuildKeywords(guild),
    canonicalUrl,
    ogType: 'website',
    jsonLd: {
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: guild.name,
      url: canonicalUrl,
      description: buildGuildDescription(guild),
      member: guild.members_count != null ? { '@type': 'QuantitativeValue', value: guild.members_count } : undefined,
      areaServed: serverName || undefined,
      knowsAbout: gameName || undefined,
    },
  };
}

function buildGuildApplicationSeo(
  formData: GuildApplicationFormData,
  origin: string,
  guildId: number,
  gameSlug?: string | null,
): PageSeoOptions {
  const guildName = formData.name.trim();
  const gameName = formData.game?.name?.trim() ?? '';
  const serverName = formData.server?.name?.trim() ?? '';
  const titleGame = gameName ? ` — ${gameName}` : '';
  const guildOrigin = buildGameOriginForSsr(origin, gameSlug ?? formData.game?.slug);
  const canonicalUrl = `${guildOrigin}/guilds/${guildId}/application-form`;
  const description = [
    `Подать заявку в гильдию «${guildName}» на gg-hub.`,
    gameName ? `Игра: ${gameName}.` : '',
    serverName ? `Сервер: ${serverName}.` : '',
    formData.is_recruiting
      ? 'Заполните анкету и отправьте заявку руководству гильдии.'
      : 'Набор в гильдию сейчас закрыт.',
  ].filter(Boolean).join(' ');
  const keywords = [
    `заявка в гильдию ${guildName}`,
    gameName ? `заявка в гильдию ${gameName}` : '',
    serverName ? `гильдия ${serverName}` : '',
    'анкета в гильдию',
    'вступить в гильдию',
    'gg-hub',
  ]
    .map((value) => value.trim())
    .filter(Boolean)
    .join(', ');

  return {
    title: `Заявка в гильдию ${guildName}${titleGame} — gg-hub`,
    description: truncateSeoText(description),
    keywords,
    canonicalUrl,
    ogType: 'website',
    jsonLd: {
      '@context': 'https://schema.org',
      '@type': 'WebPage',
      name: `Заявка в гильдию ${guildName}`,
      url: canonicalUrl,
      description: truncateSeoText(description),
      isPartOf: {
        '@type': 'WebSite',
        name: 'gg-hub',
        url: guildOrigin,
      },
    },
  };
}

function buildGamesCatalogSeo(games: GameCatalogItem[], origin: string): PageSeoOptions {
  const canonicalUrl = `${origin}/games`;
  const title = 'Игры — gg-hub';
  const description =
    'Список поддерживаемых игр на gg-hub: выберите игру и перейдите на её сайт с каталогом гильдий, журналом и инструментами сообщества.';

  return {
    title,
    description,
    keywords: 'игры, поддерживаемые игры, MMORPG, Throne and Liberty, Aion 2, gg-hub',
    canonicalUrl,
    ogType: 'website',
    jsonLd: {
      '@context': 'https://schema.org',
      '@type': 'CollectionPage',
      name: title,
      description,
      url: canonicalUrl,
      mainEntity: {
        '@type': 'ItemList',
        itemListElement: games.map((game, index) => ({
          '@type': 'ListItem',
          position: index + 1,
          name: game.name,
          url: buildGameOriginForSsr(origin, game.slug),
        })),
      },
    },
  };
}

async function resolveRouteSeo(url: string, origin: string): Promise<PageSeoOptions | undefined> {
  const path = url.split('?')[0] ?? url;
  if (path === '/' || path === '') {
    const siteContext = useSiteContextStore();
    const gameName = siteContext.game?.name?.trim();
    const pageData = useSsrPageDataStore();
    if (siteContext.game?.id) {
      try {
        pageData.setJournalPosts(await postsApi.getGlobalJournalPosts(siteContext.game.id));
      } catch {
        pageData.setJournalPosts([]);
      }
    } else {
      pageData.setJournalPosts([]);
    }

    if (!siteContext.isGameSubdomain || !gameName) return undefined;

    return {
      title: `Журнал — ${gameName} — gg-hub`,
      description: `Журнал gg-hub: новости, гайды и обновления по игре ${gameName}. Следите за событиями и публикациями сообщества.`,
      canonicalUrl: `${origin}/`,
      keywords: `журнал ${gameName}, новости ${gameName}, гайды ${gameName}, ${gameName} гильдии, gg-hub`,
      ogType: 'website',
    };
  }

  if (path === '/guilds' || path === '/guilds/') {
    const siteContext = useSiteContextStore();
    const gameName = siteContext.game?.name?.trim();
    const title = gameName ? `Каталог гильдий — ${gameName} — gg-hub` : 'Каталог гильдий — gg-hub';
    const description = gameName
      ? `Каталог гильдий по игре ${gameName}: поиск по названию, локализации, серверу и тегам. Найдите гильдию или создайте свою на gg-hub.`
      : 'Каталог гильдий MMORPG: фильтры по игре, локализации, серверу и тегам. Найдите гильдию или создайте свою на gg-hub.';
    const keywords = gameName
      ? `гильдии ${gameName}, каталог гильдий ${gameName}, найти гильдию ${gameName}, ${gameName} кланы, gg-hub`
      : 'каталог гильдий, гильдии MMORPG, найти гильдию, поиск гильдии, клан MMORPG, gg-hub';

    return {
      title,
      description,
      keywords,
      canonicalUrl: `${origin}/guilds`,
      ogType: 'website',
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': 'CollectionPage',
        name: title,
        description,
        url: `${origin}/guilds`,
      },
    };
  }

  if (path === '/games' || path === '/games/') {
    const pageData = useSsrPageDataStore();
    const games = await gamesApi.getGamesCatalog().catch(() => []);
    pageData.setGamesCatalog(games);
    return buildGamesCatalogSeo(games, origin);
  }

  const guildInfoMatch = path.match(/^\/guilds\/(\d+)\/info\/?$/);
  if (guildInfoMatch?.[1]) {
    const guildId = Number(guildInfoMatch[1]);
    if (Number.isFinite(guildId)) {
      const guild = await guildsApi.getGuild(guildId);
      useSsrPageDataStore().setGuildInfo(guild);
      return buildGuildInfoSeo(guild, origin);
    }
  }

  const applicationFormMatch = path.match(/^\/guilds\/(\d+)\/application-form\/?$/);
  if (applicationFormMatch?.[1]) {
    const guildId = Number(applicationFormMatch[1]);
    if (Number.isFinite(guildId)) {
      const formData = await guildsApi.getGuildApplicationForm(guildId);
      useSsrPageDataStore().setGuildApplicationForm(formData);
      let gameSlug = formData.game?.slug;
      if (!gameSlug) {
        const guild = await guildsApi.getGuild(guildId).catch(() => null);
        gameSlug = guild?.game?.slug;
      }
      return buildGuildApplicationSeo(formData, origin, guildId, gameSlug);
    }
  }

  const match = path.match(/^\/posts\/(\d+)\/?$/);
  if (!match) return undefined;
  const idRaw = match[1];
  if (!idRaw) return undefined;
  const postId = Number(idRaw);
  if (!Number.isFinite(postId)) return undefined;

  const post = await postsApi.getGlobalPost(postId);
  useSsrPageDataStore().setGlobalPost(post);
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

      const routeName = router.currentRoute.value.name;
      const siteContext = useSiteContextStore();
      if (routeName === 'games' && siteContext.isGameSubdomain) {
        setActiveRouter(null);
        return {
          html: '',
          piniaState: {},
          redirect: `${computeMainSiteOriginForSsr({ host: opts.host, protocol: opts.protocol })}${resolvedFullPath}`,
        };
      }

      const origin = computeRequestOriginForSsr({ host: opts.host, protocol: opts.protocol });
      const routeSeo = await resolveRouteSeo(url, origin).catch(() => {
        const path = url.split('?')[0] ?? url;
        if (path === '/' || path === '') {
          useSsrPageDataStore().setJournalPosts([]);
        }
        return undefined;
      });
      const ssrContext: { pageSeo?: PageSeoOptions } = {};
      const html = await renderToString(app, ssrContext);
      const piniaState = pinia.state.value as Record<string, unknown>;
      const pageSeo = ssrContext.pageSeo ?? routeSeo;
      const head = pageSeo ? buildPageSeoHead(pageSeo) : undefined;
      const statusCode = routeName === 'not-found' || routeName === 'page-not-found' ? 404 : 200;

      setActiveRouter(null);

      return { html, piniaState, head, statusCode };
    },
  );
}
