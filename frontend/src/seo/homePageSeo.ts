/**
 * Единый источник SEO-данных главной: Vue (usePageSeo) и сборка (inject в index.html).
 */

export const HOME_PAGE_SEO_TITLE =
  'gg-hub — платформа гильдий MMORPG | Throne and Liberty, Aion 2, Black Desert';

export const HOME_PAGE_SEO_DESCRIPTION =
  'gg-hub — бесплатная платформа для игроков MMORPG и гильдий: поиск команды, Throne and Liberty, Aion 2, Black Desert, заявки в гильдию, рейды, календарь событий, ростер, блог и голосования. Русскоязычное сообщество.';

export const HOME_PAGE_SEO_KEYWORDS =
  'гильдия MMORPG, найти гильдию, поиск гильдии, Throne and Liberty гильдия, Aion 2 гильдия, Black Desert гильдия, рекрутинг гильдии, клан MMORPG, рейды MMORPG, календарь ивентов, заявка в гильдию, gg-hub';

/** Видимый лид: согласован с модалкой «в разработке». */
export const HOME_PAGE_LEAD =
  'G-HUB — платформа для игроков MMORPG: находи гильдии в Throne and Liberty, Aion 2 и Black Desert, собирай рейды, веди календарь событий и управляй сообществом — всё в одном месте.';

export const HOME_ORGANIZATION_DESCRIPTION =
  'Платформа для организации игровых гильдий и игроков MMORPG.';

/** Публичный путь к герою (в проде файлы лежат в public/accets/images/ — опечатка в имени папки сохранена для совместимости). */
export const HOME_HERO_IMAGE_PATH = '/assets/images/1.webp';

export const DEFAULT_PRODUCTION_ORIGIN = 'https://gg-hub.ru';

export function normalizeSiteOrigin(raw: string | undefined, fallback: string): string {
  const base = fallback.replace(/\/$/, '');
  if (raw && /^https?:\/\//i.test(raw.trim())) {
    return raw.trim().replace(/\/$/, '');
  }
  return base;
}

export interface HomeJsonLdOptions {
  ogImageUrl?: string;
  /** Абсолютный URL логотипа для Organization (например PNG 512×512). */
  logoUrl?: string;
  /** Ссылки на соцсети и т.п. */
  sameAs?: string[];
  contactEmail?: string;
}

export function buildHomeCanonicalUrl(siteOrigin: string): string {
  return `${siteOrigin}/`;
}

export function buildHomeJsonLdGraph(
  siteOrigin: string,
  options: HomeJsonLdOptions = {},
): Record<string, unknown>[] {
  const canonicalUrl = buildHomeCanonicalUrl(siteOrigin);
  const heroImageUrl = `${siteOrigin}${HOME_HERO_IMAGE_PATH}`;
  const logoUrl = options.logoUrl ?? `${siteOrigin}/favicon.ico`;

  const organization: Record<string, unknown> = {
    '@type': 'Organization',
    '@id': `${siteOrigin}/#organization`,
    name: 'gg-hub',
    url: canonicalUrl,
    description: HOME_ORGANIZATION_DESCRIPTION,
    logo: {
      '@type': 'ImageObject',
      url: logoUrl,
    },
  };

  if (options.sameAs?.length) {
    organization.sameAs = options.sameAs;
  }
  if (options.contactEmail) {
    organization.contactPoint = {
      '@type': 'ContactPoint',
      email: options.contactEmail,
      contactType: 'customer support',
    };
  }

  const website: Record<string, unknown> = {
    '@type': 'WebSite',
    '@id': `${siteOrigin}/#website`,
    name: 'gg-hub',
    url: canonicalUrl,
    description: HOME_PAGE_SEO_DESCRIPTION,
    inLanguage: 'ru-RU',
    publisher: { '@id': `${siteOrigin}/#organization` },
    potentialAction: {
      '@type': 'SearchAction',
      target: {
        '@type': 'EntryPoint',
        urlTemplate: `${siteOrigin}/guilds?name={search_term_string}`,
      },
      'query-input': 'required name=search_term_string',
    },
  };

  const webPage: Record<string, unknown> = {
    '@type': 'WebPage',
    '@id': `${siteOrigin}/#webpage`,
    url: canonicalUrl,
    name: HOME_PAGE_SEO_TITLE,
    description: HOME_PAGE_SEO_DESCRIPTION,
    inLanguage: 'ru-RU',
    isPartOf: { '@id': `${siteOrigin}/#website` },
    primaryImageOfPage: {
      '@type': 'ImageObject',
      url: heroImageUrl,
    },
  };

  return [website, organization, webPage];
}

function escapeAttr(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');
}

/**
 * Фрагмент для вставки в <head> при сборке (первый HTML с полными meta + JSON-LD).
 */
export function buildHomePageStaticHeadHtml(siteOrigin: string, env: Record<string, string>): string {
  const canonicalUrl = buildHomeCanonicalUrl(siteOrigin);
  const ogImage = env.VITE_OG_IMAGE_URL?.trim();
  const logoUrl = env.VITE_ORGANIZATION_LOGO_URL?.trim();
  const sameAsRaw = env.VITE_ORG_SAME_AS?.trim();
  const sameAs = sameAsRaw
    ? sameAsRaw
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean)
    : [];
  const contactEmail = env.VITE_ORG_CONTACT_EMAIL?.trim();

  const graph = buildHomeJsonLdGraph(siteOrigin, {
    ogImageUrl: ogImage,
    logoUrl,
    sameAs: sameAs.length ? sameAs : undefined,
    contactEmail,
  });
  const jsonLd = { '@context': 'https://schema.org', '@graph': graph };

  const parts: string[] = [];
  parts.push(`<title>${escapeAttr(HOME_PAGE_SEO_TITLE)}</title>`);
  parts.push(`<meta name="description" content="${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}" />`);
  parts.push(`<meta name="keywords" content="${escapeAttr(HOME_PAGE_SEO_KEYWORDS)}" />`);
  parts.push(
    '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />',
  );
  parts.push('<meta name="author" content="gg-hub" />');
  parts.push('<meta property="og:type" content="website" />');
  parts.push('<meta property="og:locale" content="ru_RU" />');
  parts.push(`<meta property="og:url" content="${escapeAttr(canonicalUrl)}" />`);
  parts.push(`<meta property="og:title" content="${escapeAttr(HOME_PAGE_SEO_TITLE)}" />`);
  parts.push(`<meta property="og:description" content="${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}" />`);
  parts.push('<meta property="og:site_name" content="gg-hub" />');
  if (ogImage) {
    parts.push(`<meta property="og:image" content="${escapeAttr(ogImage)}" />`);
    parts.push(`<meta property="og:image:alt" content="${escapeAttr(HOME_PAGE_SEO_TITLE)}" />`);
  }
  parts.push(`<meta name="twitter:card" content="${ogImage ? 'summary_large_image' : 'summary'}" />`);
  parts.push(`<meta name="twitter:title" content="${escapeAttr(HOME_PAGE_SEO_TITLE)}" />`);
  parts.push(`<meta name="twitter:description" content="${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}" />`);
  if (ogImage) {
    parts.push(`<meta name="twitter:image" content="${escapeAttr(ogImage)}" />`);
  }
  parts.push(`<link rel="canonical" href="${escapeAttr(canonicalUrl)}" />`);
  parts.push(
    `<link rel="preload" as="image" href="${escapeAttr(`${siteOrigin}${HOME_HERO_IMAGE_PATH}`)}" fetchpriority="high" />`,
  );
  parts.push(`<script type="application/ld+json" id="gg-hub-ld-json">${JSON.stringify(jsonLd)}</script>`);

  return `\n    ${parts.join('\n    ')}\n    `;
}

export function buildHomeNoscriptHtml(): string {
  return `
    <noscript>
      <div style="padding:1rem;max-width:42rem;margin:0 auto;font-family:system-ui,sans-serif">
        <h1>${escapeAttr(HOME_PAGE_SEO_TITLE)}</h1>
        <p>${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}</p>
        <p><a href="/guilds">Каталог гильдий</a></p>
      </div>
    </noscript>`.trim();
}
