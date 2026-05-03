/**
 * Единый источник SEO-данных главной: Vue (usePageSeo) и сборка (inject в index.html).
 */

export const HOME_PAGE_SEO_TITLE =
  'Управление гильдией MMORPG — gg-hub: Throne and Liberty, Aion 2';

export const HOME_PAGE_SEO_DESCRIPTION =
  'Управление гильдией в MMORPG: ростер, заявки, рейды, календарь событий, блог, голосования. Платформа gg-hub для Throne and Liberty и Aion 2.';

export const HOME_PAGE_SEO_KEYWORDS =
  'управление гильдией, управление гильдией MMORPG, инструменты для управления гильдией, менеджмент гильдии, CRM для гильдии, управление кланом, гильдия MMORPG, найти гильдию, поиск гильдии, Throne and Liberty гильдия, Aion 2 гильдия, рекрутинг гильдии, клан MMORPG, рейды MMORPG, календарь ивентов, заявка в гильдию, gg-hub';

/** Видимый лид: согласован с SEO-описанием и содержит ключ «управление гильдией». */
export const HOME_PAGE_LEAD =
  'gg-hub — платформа для управления гильдией в MMORPG: ростер, заявки, рейды, календарь событий и блог. Throne and Liberty и Aion 2 — всё в одном месте.';

export const HOME_ORGANIZATION_DESCRIPTION =
  'Платформа для управления гильдией в MMORPG: инструменты для лидеров, офицеров и игроков.';

/**
 * Частые вопросы про управление гильдией — единый источник для видимого FAQ-блока
 * в UI и FAQPage JSON-LD. Меняя тексты здесь, синхронно обновляем и то и другое.
 */
export interface HomeFaqItem {
  question: string;
  answer: string;
}

export const HOME_FAQ_ITEMS: HomeFaqItem[] = [
  {
    question: 'Что такое управление гильдией в gg-hub?',
    answer:
      'Управление гильдией в gg-hub — это единый веб-интерфейс для лидера и офицеров: ростер с ролями и правами, заявки на вступление, календарь рейдов и ивентов, блог, голосования и лента новостей. Всё, что обычно приходится собирать из Discord, Google-таблиц и форумов, — в одном месте.',
  },
  {
    question: 'Какие функции управления гильдией уже работают?',
    answer:
      'Уже доступны каталог гильдий, карточки гильдий и фильтры по игре и серверу. Поэтапно запускаются ростер, заявки и анкеты, календарь событий, рейды с распределением ролей, блог и голосования. Следите за анонсами — функции управления гильдией появляются без платных тарифов.',
  },
  {
    question: 'Для каких игр подходит управление гильдией на платформе?',
    answer:
      'Сейчас gg-hub поддерживает управление гильдией в Throne and Liberty и Aion 2: можно указывать сервер, класс и роль персонажа, фильтровать игроков и вести раздельные пространства по каждой игре.',
  },
  {
    question: 'Как лидеру начать управление гильдией в gg-hub?',
    answer:
      'Создайте профиль гильдии, добавьте название, игру и сервер, пригласите офицеров и настройте роли. Дальше можно заполнять ростер, публиковать анкеты для рекрутинга, планировать рейды в календаре и вести блог — все инструменты управления гильдией доступны из единого интерфейса.',
  },
  {
    question: 'Как перейти на управление гильдией через gg-hub из Discord?',
    answer:
      'Создайте профиль гильдии, перенесите ростер и роли, добавьте офицеров. Discord можно оставить для голосового общения, а управление гильдией — расписание рейдов, заявки, блог и голосования — вести на gg-hub, где всё структурировано и ищется по фильтрам.',
  },
  {
    question: 'Подходит ли управление гильдией для офицеров и рекрутеров?',
    answer:
      'Да. Офицеры получают настраиваемые анкеты, комментарии к заявкам, историю участников и отдельные права доступа. Рекрутеры могут публиковать объявления в блоге гильдии, а лидеры — контролировать состав и планировать события через общий календарь.',
  },
];

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
    breadcrumb: { '@id': `${siteOrigin}/#breadcrumb` },
    mainEntity: { '@id': `${siteOrigin}/#software-application` },
  };

  const softwareApplication: Record<string, unknown> = {
    '@type': 'SoftwareApplication',
    '@id': `${siteOrigin}/#software-application`,
    name: 'gg-hub — управление гильдией',
    alternateName: 'gg-hub',
    description:
      'Веб-платформа для управления гильдией в MMORPG: ростер, заявки, рейды, календарь событий, блог и голосования.',
    url: canonicalUrl,
    applicationCategory: 'BusinessApplication',
    applicationSubCategory: 'Guild Management',
    operatingSystem: 'Web',
    inLanguage: 'ru-RU',
    image: heroImageUrl,
    publisher: { '@id': `${siteOrigin}/#organization` },
    featureList: [
      'Управление гильдией: ростер, роли и права доступа',
      'Заявки и настраиваемые анкеты для вступления',
      'Календарь рейдов, ивентов и PvP-активностей',
      'Рейды с распределением ролей в составе',
      'Блог гильдии и лента новостей',
      'Голосования внутри гильдии',
      'Единый профиль игрока и персонажи из Throne and Liberty и Aion 2',
    ],
    offers: {
      '@type': 'Offer',
      price: '0',
      priceCurrency: 'RUB',
      availability: 'https://schema.org/InStock',
    },
  };

  const service: Record<string, unknown> = {
    '@type': 'Service',
    '@id': `${siteOrigin}/#service-guild-management`,
    name: 'Управление гильдией MMORPG',
    serviceType: 'Управление гильдией MMORPG',
    description:
      'Онлайн-сервис для управления гильдией в MMORPG: ростер, заявки, рейды, календарь и блог для лидеров и офицеров гильдий Throne and Liberty и Aion 2.',
    provider: { '@id': `${siteOrigin}/#organization` },
    areaServed: 'RU',
    url: canonicalUrl,
    availableLanguage: 'ru-RU',
  };

  const breadcrumb: Record<string, unknown> = {
    '@type': 'BreadcrumbList',
    '@id': `${siteOrigin}/#breadcrumb`,
    itemListElement: [
      {
        '@type': 'ListItem',
        position: 1,
        name: 'Главная',
        item: canonicalUrl,
      },
    ],
  };

  const faqPage: Record<string, unknown> = {
    '@type': 'FAQPage',
    '@id': `${siteOrigin}/#faq`,
    inLanguage: 'ru-RU',
    isPartOf: { '@id': `${siteOrigin}/#website` },
    mainEntity: HOME_FAQ_ITEMS.map((item) => ({
      '@type': 'Question',
      name: item.question,
      acceptedAnswer: {
        '@type': 'Answer',
        text: item.answer,
      },
    })),
  };

  return [website, organization, webPage, softwareApplication, service, breadcrumb, faqPage];
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
    parts.push('<meta property="og:image:width" content="1200" />');
    parts.push('<meta property="og:image:height" content="630" />');
    parts.push(`<meta property="og:image:alt" content="${escapeAttr(HOME_PAGE_SEO_TITLE)}" />`);
  }
  parts.push(`<meta name="twitter:card" content="${ogImage ? 'summary_large_image' : 'summary'}" />`);
  parts.push(`<meta name="twitter:title" content="${escapeAttr(HOME_PAGE_SEO_TITLE)}" />`);
  parts.push(`<meta name="twitter:description" content="${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}" />`);
  if (ogImage) {
    parts.push(`<meta name="twitter:image" content="${escapeAttr(ogImage)}" />`);
  }
  parts.push('<meta name="geo.region" content="RU" />');
  parts.push('<meta name="geo.placename" content="Russia" />');
  parts.push(`<link rel="canonical" href="${escapeAttr(canonicalUrl)}" />`);
  parts.push(`<link rel="alternate" hreflang="ru" href="${escapeAttr(canonicalUrl)}" />`);
  parts.push(`<link rel="alternate" hreflang="x-default" href="${escapeAttr(canonicalUrl)}" />`);
  /* Относительный URL = тот же ресурс, что у <img src>, без расхождения с origin в dev / зеркалах */
  parts.push(
    `<link rel="preload" as="image" href="${escapeAttr(HOME_HERO_IMAGE_PATH)}" fetchpriority="high" />`,
  );
  parts.push(`<script type="application/ld+json" id="gg-hub-ld-json">${JSON.stringify(jsonLd)}</script>`);

  return `\n    ${parts.join('\n    ')}\n    `;
}

export function buildHomeNoscriptHtml(): string {
  const faqHtml = HOME_FAQ_ITEMS.map(
    (item) =>
      `<section><h3>${escapeAttr(item.question)}</h3><p>${escapeAttr(item.answer)}</p></section>`,
  ).join('');

  return `
    <noscript>
      <div style="padding:1rem;max-width:42rem;margin:0 auto;font-family:system-ui,sans-serif">
        <h1>Управление гильдией MMORPG — gg-hub</h1>
        <p>${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}</p>
        <h2>Управление гильдией — все инструменты лидера в одном месте</h2>
        <p>Полный набор функций для управления гильдией: ростер, заявки и анкеты, календарь рейдов и ивентов, блог и голосования. Поддерживаем Throne and Liberty и Aion 2.</p>
        <p><a href="/guilds">Каталог гильдий</a></p>
        <h2>Частые вопросы про управление гильдией</h2>
        ${faqHtml}
      </div>
    </noscript>`.trim();
}
