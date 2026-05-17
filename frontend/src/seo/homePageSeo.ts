/**
 * Единый источник SEO-данных главной: Vue (usePageSeo) и сборка (inject в index.html).
 * Целевой запрос: «управление гильдией в MMORPG» (Google, Яндекс).
 */

/** Основной ключевой запрос (точное вхождение в title, description, H1, intro). */
export const HOME_PRIMARY_KEYWORD = 'управление гильдией в MMORPG';

export const HOME_PAGE_SEO_TITLE =
  'Управление гильдией в MMORPG — ростер, рейды, заявки и ДКП | gg-hub';

export const HOME_PAGE_SEO_DESCRIPTION =
  'gg-hub — бесплатная платформа для управления гильдией в MMORPG. Ведите состав, заявки, календарь рейдов, хранилище, ДКП, блог и голосования. Подходит для Throne and Liberty, Aion 2; список игр расширяется в админке.';

/** Видимый лид в hero: не менять без согласования — рендерится в hero. */
export const HOME_PAGE_LEAD =
  'Управление гильдией в MMORPG на gg-hub: ростер, заявки, рейды, календарь, хранилище, ДКП и блог — бесплатно, в одном интерфейсе для лидеров и игроков.';

export const HOME_ORGANIZATION_DESCRIPTION =
  'Бесплатная веб-платформа для ведения гильдии в MMORPG: инструменты для лидеров, офицеров и игроков.';

export const HOME_CAPABILITIES_HEADING = 'Что умеет gg-hub';

export interface HomeCapabilityItem {
  title: string;
  desc: string;
}

export const HOME_CAPABILITIES_ITEMS: readonly HomeCapabilityItem[] = [
  {
    title: 'Состав гильдии',
    desc: 'Ростер участников, персонажи, классы, серверы, роли и права офицеров.',
  },
  {
    title: 'Заявки и рекрутинг',
    desc: 'Настраиваемая анкета, комментарии офицеров, голосование и статусы заявок.',
  },
  {
    title: 'Календарь рейдов',
    desc: 'Разовые и повторяющиеся события, участники, отказы и напоминания.',
  },
  {
    title: 'История посещений',
    desc: 'Фиксация участия в событиях, скриншоты, заметки и начисление очков.',
  },
  {
    title: 'Хранилище и лут',
    desc: 'Предметы, тиры, остатки, выдачи участникам и журнал операций.',
  },
  {
    title: 'ДКП система',
    desc: 'Начисление, списание, ручные корректировки и баланс каждого участника.',
  },
];

/** Видимый SEO-блок под hero (не дублирует hero, расширяет тему для поисковиков). */
export const HOME_SEO_INTRO_HEADING = 'Зачем гильдии отдельная система управления';

export const HOME_SEO_INTRO_PARAGRAPHS: readonly string[] = [
  'В MMORPG гильдия быстро перерастает обычный чат. Нужно понимать, кто состоит в составе, кто приходит на рейды, какие заявки ждут решения, кому выдали предметы и сколько ДКП осталось у каждого участника.',
  'gg-hub собирает эти процессы в одном интерфейсе. Discord можно оставить для общения и голоса, а ведение гильдии перенести в структурированную систему: ростер, заявки, календарь, рейды, хранилище, ДКП, блог и голосования.',
];

export const HOME_SEO_INTRO_CTA_LABEL = 'Каталог гильдий';

export const HOME_DISCORD_BLOCK_HEADING = 'Почему не Discord и не таблицы';

export const HOME_DISCORD_BLOCK_PARAGRAPHS: readonly string[] = [
  'Discord удобен для общения, но заявки, рейды, лут и ДКП быстро теряются в каналах и личных сообщениях. Таблицы помогают вести учёт, но требуют ручного обновления и не дают ролей, анкет, календаря и истории действий.',
  'gg-hub не заменяет Discord, а дополняет его: голос и чат остаются там, а состав, заявки, события, хранилище и ДКП ведутся на сайте.',
];

export const HOME_FAQ_SECTION_HEADING = 'Частые вопросы о gg-hub';

/**
 * Частые вопросы — единый источник для FAQ-блока в UI и FAQPage JSON-LD.
 * Точная фраза «управление гильдией в MMORPG» — в первом вопросе.
 */
export interface HomeFaqItem {
  question: string;
  answer: string;
}

export const HOME_FAQ_ITEMS: HomeFaqItem[] = [
  {
    question: 'Что такое управление гильдией в MMORPG на gg-hub?',
    answer:
      'Это веб-платформа, где лидер и офицеры ведут состав, заявки, календарь, историю рейдов, хранилище, ДКП, блог и опросы в одном месте. Игроки находят гильдию в каталоге и подают заявку по анкете. gg-hub дополняет Discord: голос — там, организация — на сайте.',
  },
  {
    question: 'Как создать гильдию на gg-hub?',
    answer:
      'Зарегистрируйтесь, добавьте персонажа с игрой и сервером, затем создайте гильдию в личном кабинете: описание, роли, права и анкета заявки. После этого гильдия появится в каталоге, если включена публичная видимость.',
  },
  {
    question: 'Можно ли использовать gg-hub вместе с Discord?',
    answer:
      'Да. gg-hub не заменяет Discord: голос и чат остаются там. На сайте ведутся состав, заявки, календарь, рейды, хранилище и ДКП. Для событий можно настроить напоминания в Discord.',
  },
  {
    question: 'Можно ли настроить анкету заявки?',
    answer:
      'Да. Лидер настраивает поля анкеты: текст, выбор, файлы — ник, класс, сервер, опыт, прайм-тайм и любые свои вопросы. Офицеры обсуждают заявку в комментариях, голосуют и принимают решение.',
  },
  {
    question: 'Есть ли роли и права для офицеров?',
    answer:
      'Да. В гильдии настраиваются роли и детальные права: кто принимает заявки, ведёт рейды, управляет хранилищем или ДКП. Офицер видит только те разделы, к которым у него есть доступ.',
  },
  {
    question: 'Как работает ДКП?',
    answer:
      'ДКП включается в настройках гильдии. Очки начисляются по событиям истории рейдов, списываются при выдаче из хранилища, корректируются вручную. Есть общий журнал движений и баланс на каждого участника.',
  },
  {
    question: 'Можно ли вести несколько персонажей?',
    answer:
      'Да. На одном аккаунте можно добавить несколько персонажей в разных играх и серверах и состоять в разных гильдиях.',
  },
  {
    question: 'Нужна ли регистрация для просмотра каталога гильдий?',
    answer:
      'Нет. Каталог гильдий и карточки открыты без входа. Для подачи заявки и работы в гильдии нужна регистрация и персонаж.',
  },
  {
    question: 'Для каких MMORPG подходит gg-hub?',
    answer:
      'gg-hub кросс-игровой: список MMORPG расширяется в админке. Сейчас активны сообщества Throne and Liberty и Aion 2 — с фильтрами по серверу и классу персонажа.',
  },
  {
    question: 'Сколько стоит использование gg-hub?',
    answer:
      'Бесплатно. Регистрация и все инструменты для лидеров, офицеров и игроков доступны без платных тарифов.',
  },
];

/** Публичный путь к герою (в проде файлы лежат в public/assets/images/). */
export const HOME_HERO_IMAGE_PATH = '/assets/images/1.webp';

export const DEFAULT_PRODUCTION_ORIGIN = 'https://gg-hub.ru';

const DEFAULT_LOGO_PATH = '/favicon.svg';

export function normalizeSiteOrigin(raw: string | undefined, fallback: string): string {
  const base = fallback.replace(/\/$/, '');
  if (raw && /^https?:\/\//i.test(raw.trim())) {
    return raw.trim().replace(/\/$/, '');
  }
  return base;
}

export function resolveOgImageUrl(siteOrigin: string, envUrl?: string): string {
  const trimmed = envUrl?.trim();
  if (trimmed) {
    return trimmed;
  }
  return `${siteOrigin}${HOME_HERO_IMAGE_PATH}`;
}

export function resolveLogoUrl(siteOrigin: string, envUrl?: string): string {
  const trimmed = envUrl?.trim();
  if (trimmed) {
    return trimmed;
  }
  return `${siteOrigin}${DEFAULT_LOGO_PATH}`;
}

export interface HomeJsonLdOptions {
  ogImageUrl?: string;
  logoUrl?: string;
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
  const ogImageUrl = options.ogImageUrl ?? heroImageUrl;
  const logoUrl = resolveLogoUrl(siteOrigin, options.logoUrl);

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
    alternateName: ['gg-hub', 'gg-hub.ru'],
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
    headline: HOME_PRIMARY_KEYWORD,
    description: HOME_PAGE_SEO_DESCRIPTION,
    inLanguage: 'ru-RU',
    isPartOf: { '@id': `${siteOrigin}/#website` },
    about: {
      '@type': 'Thing',
      name: HOME_PRIMARY_KEYWORD,
    },
    primaryImageOfPage: {
      '@type': 'ImageObject',
      url: ogImageUrl,
      caption: 'gg-hub — платформа для ведения гильдии в MMORPG',
    },
    breadcrumb: { '@id': `${siteOrigin}/#breadcrumb` },
    mainEntity: { '@id': `${siteOrigin}/#software-application` },
  };

  const softwareApplication: Record<string, unknown> = {
    '@type': 'SoftwareApplication',
    '@id': `${siteOrigin}/#software-application`,
    name: 'gg-hub',
    alternateName: 'gg-hub — менеджмент гильдии в MMORPG',
    description:
      'Бесплатная веб-платформа: ростер, заявки, рейды, календарь, хранилище, ДКП, блог и голосования для гильдий в MMORPG.',
    url: canonicalUrl,
    applicationCategory: 'BusinessApplication',
    applicationSubCategory: 'Guild Management',
    operatingSystem: 'Web',
    inLanguage: 'ru-RU',
    image: ogImageUrl,
    publisher: { '@id': `${siteOrigin}/#organization` },
    featureList: [
      'Ростер гильдии и права офицеров',
      'Заявки, приглашения и настраиваемые анкеты',
      'Календарь событий и история посещений с ДКП',
      'Рейды с составом и синхронизацией',
      'Хранилище гильдии и журнал ДКП',
      'Блог и опросы',
      'Каталог гильдий и профиль игрока',
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
    name: 'Ведение гильдии в MMORPG',
    serviceType: 'Менеджмент гильдии',
    description: HOME_PAGE_SEO_DESCRIPTION,
    provider: { '@id': `${siteOrigin}/#organization` },
    areaServed: {
      '@type': 'Country',
      name: 'Россия',
    },
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
        name: 'gg-hub',
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
  const ogImage = resolveOgImageUrl(siteOrigin, env.VITE_OG_IMAGE_URL);
  const logoUrl = resolveLogoUrl(siteOrigin, env.VITE_ORGANIZATION_LOGO_URL);
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
  parts.push(
    '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />',
  );
  parts.push('<meta name="author" content="gg-hub" />');
  parts.push('<meta name="language" content="Russian" />');
  parts.push('<meta property="og:type" content="website" />');
  parts.push('<meta property="og:locale" content="ru_RU" />');
  parts.push(`<meta property="og:url" content="${escapeAttr(canonicalUrl)}" />`);
  parts.push(`<meta property="og:title" content="${escapeAttr(HOME_PAGE_SEO_TITLE)}" />`);
  parts.push(`<meta property="og:description" content="${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}" />`);
  parts.push('<meta property="og:site_name" content="gg-hub" />');
  parts.push(`<meta property="og:image" content="${escapeAttr(ogImage)}" />`);
  parts.push('<meta property="og:image:width" content="1200" />');
  parts.push('<meta property="og:image:height" content="630" />');
  parts.push(
    `<meta property="og:image:alt" content="${escapeAttr('gg-hub — платформа для ведения гильдии в MMORPG')}" />`,
  );
  parts.push('<meta name="twitter:card" content="summary_large_image" />');
  parts.push(`<meta name="twitter:title" content="${escapeAttr(HOME_PAGE_SEO_TITLE)}" />`);
  parts.push(`<meta name="twitter:description" content="${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}" />`);
  parts.push(`<meta name="twitter:image" content="${escapeAttr(ogImage)}" />`);
  parts.push('<meta name="geo.region" content="RU" />');
  parts.push('<meta name="geo.placename" content="Russia" />');
  parts.push(`<link rel="canonical" href="${escapeAttr(canonicalUrl)}" />`);
  parts.push(`<link rel="alternate" hreflang="ru" href="${escapeAttr(canonicalUrl)}" />`);
  parts.push(`<link rel="alternate" hreflang="x-default" href="${escapeAttr(canonicalUrl)}" />`);
  parts.push(
    `<link rel="preload" as="image" href="${escapeAttr(HOME_HERO_IMAGE_PATH)}" fetchpriority="high" />`,
  );
  parts.push(`<script type="application/ld+json" id="gg-hub-ld-json">${JSON.stringify(jsonLd)}</script>`);

  return `\n    ${parts.join('\n    ')}\n    `;
}

export function buildHomeNoscriptHtml(): string {
  const capabilitiesHtml = HOME_CAPABILITIES_ITEMS.map(
    (item) => `<li><strong>${escapeAttr(item.title)}</strong> — ${escapeAttr(item.desc)}</li>`,
  ).join('');
  const introHtml = HOME_SEO_INTRO_PARAGRAPHS.map((p) => `<p>${escapeAttr(p)}</p>`).join('');
  const discordHtml = HOME_DISCORD_BLOCK_PARAGRAPHS.map((p) => `<p>${escapeAttr(p)}</p>`).join('');
  const faqHtml = HOME_FAQ_ITEMS.map(
    (item) =>
      `<section><h3>${escapeAttr(item.question)}</h3><p>${escapeAttr(item.answer)}</p></section>`,
  ).join('');

  return `
    <noscript>
      <div style="padding:1rem;max-width:42rem;margin:0 auto;font-family:system-ui,sans-serif">
        <h1>Управление гильдией в MMORPG — gg-hub</h1>
        <p>${escapeAttr(HOME_PAGE_SEO_DESCRIPTION)}</p>
        <h2>${escapeAttr(HOME_CAPABILITIES_HEADING)}</h2>
        <ul>${capabilitiesHtml}</ul>
        <h2>${escapeAttr(HOME_SEO_INTRO_HEADING)}</h2>
        ${introHtml}
        <p><a href="/guilds">${escapeAttr(HOME_SEO_INTRO_CTA_LABEL)}</a></p>
        <h2>${escapeAttr(HOME_DISCORD_BLOCK_HEADING)}</h2>
        ${discordHtml}
        <h2>${escapeAttr(HOME_FAQ_SECTION_HEADING)}</h2>
        ${faqHtml}
      </div>
    </noscript>`.trim();
}
