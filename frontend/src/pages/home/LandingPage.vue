<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, defineAsyncComponent } from 'vue';
import { storeToRefs } from 'pinia';
import { useThemeStore } from '@/stores/theme';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import { RouterLink } from 'vue-router';
import { usePageSeo, getSiteOrigin } from '@/shared/lib/usePageSeo';
import { recordLandingCtaClick, type LandingCtaButton } from '@/shared/api/landingApi';
import { gamesApi } from '@/shared/api/gamesApi';
import {
  HOME_PAGE_SEO_TITLE,
  HOME_PAGE_SEO_DESCRIPTION,
  HOME_PAGE_LEAD,
  HOME_HERO_IMAGE_PATH,
  HOME_HERO_MOBILE_IMAGE_PATH,
  HOME_FAQ_ITEMS,
  HOME_FAQ_SECTION_HEADING,
  HOME_CAPABILITIES_HEADING,
  HOME_CAPABILITIES_ITEMS,
  HOME_SEO_INTRO_HEADING,
  HOME_SEO_INTRO_PARAGRAPHS,
  HOME_SEO_INTRO_CTA_LABEL,
  HOME_DISCORD_BLOCK_HEADING,
  HOME_DISCORD_BLOCK_PARAGRAPHS,
  buildHomeCanonicalUrl,
  buildHomeJsonLdGraph,
  resolveOgImageUrl,
} from '@/seo/homePageSeo';

const siteOrigin = getSiteOrigin();
const canonicalUrl = buildHomeCanonicalUrl(siteOrigin);

const ogImageEnv = (import.meta.env.VITE_OG_IMAGE_URL as string | undefined)?.trim();
const logoUrlEnv = (import.meta.env.VITE_ORGANIZATION_LOGO_URL as string | undefined)?.trim();
const sameAsRaw = (import.meta.env.VITE_ORG_SAME_AS as string | undefined)?.trim();
const sameAsList = sameAsRaw
  ? sameAsRaw
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean)
  : [];
const contactEmailEnv = (import.meta.env.VITE_ORG_CONTACT_EMAIL as string | undefined)?.trim();

const resolvedOgImage = resolveOgImageUrl(siteOrigin, ogImageEnv);

usePageSeo({
  title: HOME_PAGE_SEO_TITLE,
  description: HOME_PAGE_SEO_DESCRIPTION,
  canonicalUrl,
  ogImageUrl: resolvedOgImage,
  jsonLd: buildHomeJsonLdGraph(siteOrigin, {
    ogImageUrl: resolvedOgImage,
    logoUrl: logoUrlEnv,
    sameAs: sameAsList.length ? sameAsList : undefined,
    contactEmail: contactEmailEnv,
  }),
});

const heroImageAlt =
  'Управление гильдией в MMORPG на платформе gg-hub: ростер, рейды, календарь, хранилище, ДКП, заявки и блог гильдии';
const homeCtaImagePath = '/assets/images/2_2_2.webp';
const homeCtaImageAlt = '';

const gamesFallback = [
  { name: 'Throne and Liberty', slug: 'throne-and-liberty' },
  { name: 'Aion 2', slug: 'aion-2' },
] as const;

const games = ref<{ name: string; slug: string; id?: number }[]>([...gamesFallback]);


onMounted(async () => {
  try {
    const list = await gamesApi.getGames();
    const active = list.filter((g) => g.is_active);
    if (active.length > 0) {
      games.value = active.map((g) => ({ name: g.name, slug: g.slug, id: g.id }));
    } else {
      games.value = [...gamesFallback];
    }
  } catch {
    games.value = [...gamesFallback];
  }
});

function guildsLinkForGame(game: { id?: number }) {
  if (game.id != null) {
    return { path: '/guilds' as const, query: { game_id: String(game.id) } };
  }
  return '/guilds';
}

const playerBenefits = [
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>`,
    title: 'Найди свою гильдию',
    desc: 'Каталог с фильтрами по игре и серверу, карточка гильдии и публичная форма заявки — выбирай осознанно, без случайных инвайтов.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
    title: 'Единый профиль игрока',
    desc: 'Все персонажи из разных MMORPG на одном аккаунте: игра, сервер, класс и теги.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>`,
    title: 'Будь в курсе',
    desc: 'Лента постов, календарь гильдии, in-app уведомления и опционально Discord — не пропустишь рейд или решение офицеров.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>`,
    title: 'Кросс-игровой аккаунт',
    desc: 'Играешь в несколько MMO — переключай игру в шапке сайта, веди отдельных персонажей и гильдии без новых регистраций.',
  },
];

const guildBenefits = [
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>`,
    title: 'Состав и роли',
    desc: 'Лидер видит весь ростер, роли и активность. Офицер работает только в разделах, к которым выданы права.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>`,
    title: 'Календарь и история',
    desc: 'Офицер добавляет разовые и повторяющиеся события, собирает участников и фиксирует посещение с начислением ДКП.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>`,
    title: 'Заявки и рекрутинг',
    desc: 'Лидер настраивает анкету: ник, класс, сервер, опыт, прайм-тайм. Офицеры обсуждают заявку, голосуют и принимают решение.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 11.73V22"/><path d="M12 22V11.76l8.7-5.09"/></svg>`,
    title: 'Хранилище и ДКП',
    desc: 'Казначей добавляет предмет, выбирает участника и причину выдачи — операция попадает в журнал, ДКП списывается при необходимости.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"/><path d="m15 9 6-6"/></svg>`,
    title: 'Рейды',
    desc: 'Рейд-лид создаёт рейд, добавляет группы и слоты, распределяет персонажей. Участники с открытой страницей видят обновления без скриншотов.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`,
    title: 'Блог и опросы',
    desc: 'Публикуйте новости гильдии и проводите голосования по составу, луту и правилам — игроки видят ленту в интерфейсе гильдии.',
  },
];

const roleScenarios = [
  { role: 'Лидер', text: 'Контроль состава, роли, заявки и настройки ДКП.' },
  { role: 'Офицер', text: 'Обработка заявок, рейды, банк и события в рамках выданных прав.' },
  { role: 'Рейд-лид', text: 'Сбор состава по слотам и фиксация посещения.' },
  { role: 'Казначей', text: 'Выдача предметов и ведение ДКП без отдельных таблиц.' },
  { role: 'Игрок', text: 'Поиск гильдии, анкета, календарь, уведомления и персонажи.' },
];

const platformHighlights = [
  { value: '0 ₽', label: 'Стоимость для гильдий и игроков' },
  { value: '24/7', label: 'Доступ через браузер' },
  { value: '∞', label: 'Персонажей на одном аккаунте' },
];

const features = [
  {
    title: 'Каталог гильдий',
    desc: 'Игрок фильтрует список по игре и серверу, открывает карточку и подаёт заявку — без регистрации можно только просматривать каталог.',
  },
  {
    title: 'Состав и права',
    desc: 'Лидер видит весь состав и роли; офицеру выдают права только на нужные разделы — заявки, рейды или хранилище.',
  },
  {
    title: 'Заявки и приглашения',
    desc: 'Офицер принимает заявку после обсуждения в комментариях и голосования; игрок отслеживает статус в личном кабинете.',
  },
  {
    title: 'Календарь событий',
    desc: 'Офицер создаёт разовые и повторяющиеся события; участники отмечают участие, приходят напоминания в Discord.',
  },
  {
    title: 'История событий',
    desc: 'После рейда фиксируется посещаемость, прикрепляются скриншоты; очки ДКП начисляются по шаблону события.',
  },
  {
    title: 'Рейды',
    desc: 'Рейд-лид собирает состав по слотам из персонажей гильдии; изменения видят все, у кого открыта страница рейда.',
  },
  {
    title: 'Хранилище и ДКП',
    desc: 'Казначей выдаёт предмет и при необходимости списывает ДКП; в журнале сохраняется причина и баланс участника.',
  },
  {
    title: 'Блог и журнал',
    desc: 'Игрок видит новости гильдии и игры; лидер публикует посты и модерирует комментарии.',
  },
  {
    title: 'Опросы и уведомления',
    desc: 'Голосования по решениям гильдии; in-app уведомления и push в браузере о заявках и событиях.',
  },
];

const steps = [
  {
    num: '01',
    title: 'Создайте аккаунт и персонажа',
    desc: 'Укажите игру, сервер, класс и ник. На одном аккаунте можно вести несколько персонажей.',
    link: { to: '/register', label: 'Регистрация' },
  },
  {
    num: '02',
    title: 'Найдите или создайте гильдию',
    desc: 'Игрок подаёт заявку через каталог, лидер создаёт гильдию, описание, роли и анкету.',
    link: { to: '/guilds', label: 'Каталог гильдий' },
  },
  {
    num: '03',
    title: 'Настройте управление',
    desc: 'Назначьте офицеров, выдайте права, добавьте события, рейды и правила ДКП.',
    link: null,
  },
  {
    num: '04',
    title: 'Ведите активность гильдии',
    desc: 'Фиксируйте посещения, распределяйте лут, ведите хранилище, публикуйте новости и проводите голосования.',
    link: null,
  },
];

// --- Scroll-reveal ---
const visible = ref<Set<string>>(new Set());
let observer: IntersectionObserver | null = null;
const sectionEls = new Map<string, HTMLElement>();

function setRef(id: string) {
  return (el: unknown) => {
    const node = el as HTMLElement | null;
    if (node) {
      node.dataset.revealId = id;
      sectionEls.set(id, node);
      observer?.observe(node);
    }
  };
}

function show(id: string) {
  return visible.value.has(id);
}

const playersIntroAnchorEl = ref<HTMLElement | null>(null);

function playersHeaderRef(el: unknown) {
  setRef('players-header')(el);
  playersIntroAnchorEl.value = (el as HTMLElement | null) ?? null;
}

onMounted(() => {
  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const id = (entry.target as HTMLElement).dataset.revealId;
          if (id) {
            visible.value = new Set([...visible.value, id]);
            observer?.unobserve(entry.target);
          }
        }
      });
    },
    { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
  );
  sectionEls.forEach((el) => observer?.observe(el));
});

onUnmounted(() => observer?.disconnect());

// --- Parallax for hero decorations ---
const mouseX = ref(0);
const mouseY = ref(0);

function handleMouseMove(e: MouseEvent) {
  mouseX.value = (e.clientX / window.innerWidth - 0.5) * 20;
  mouseY.value = (e.clientY / window.innerHeight - 0.5) * 20;
}

onMounted(() => window.addEventListener('mousemove', handleMouseMove, { passive: true }));
onUnmounted(() => window.removeEventListener('mousemove', handleMouseMove));

const themeStore = useThemeStore();
const { isDark } = storeToRefs(themeStore);

const devModalOpen = ref(false);
const LandingDevModal = defineAsyncComponent(() => import('./LandingDevModal.vue'));

function openLandingCtaModal(button: LandingCtaButton) {
  devModalOpen.value = true;
  void recordLandingCtaClick(button).catch(() => {
    /* запись в БД — best effort, модалка уже показана */
  });
}

function closeLandingCtaModal() {
  devModalOpen.value = false;
}

/** Фон лендинга при скролле: смешение двух оттенков после пересечения якоря (палитра зависит от темы) */
const LANDING_SCROLL_PALETTE_LIGHT = {
  from: { r: 255, g: 241, b: 211 },
  to: { r: 146, g: 178, b: 207 },
  alpha: 0.4,
};
const LANDING_SCROLL_PALETTE_DARK = {
  from: { r: 24, g: 26, b: 32 },
  to: { r: 38, g: 48, b: 64 },
  alpha: 0.78,
};

function landingScrollPalette() {
  return isDark.value ? LANDING_SCROLL_PALETTE_DARK : LANDING_SCROLL_PALETTE_LIGHT;
}

const landingScrollBg = ref(
  `rgba(${LANDING_SCROLL_PALETTE_LIGHT.from.r}, ${LANDING_SCROLL_PALETTE_LIGHT.from.g}, ${LANDING_SCROLL_PALETTE_LIGHT.from.b}, ${LANDING_SCROLL_PALETTE_LIGHT.alpha})`,
);
let landingScrollRaf = 0;
let landingScrollRafPending = false;

function landingBgBlendRangePx(): number {
  if (typeof window === 'undefined') return 720;
  const h = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
  const maxY = Math.max(1, h - window.innerHeight);
  return Math.min(Math.max(window.innerHeight * 1.2, 520), Math.max(640, maxY * 0.55));
}

function applyLandingScrollBackground() {
  landingScrollRafPending = false;
  const { from, to, alpha: a } = landingScrollPalette();
  const el = playersIntroAnchorEl.value;

  if (!el || typeof window === 'undefined') {
    landingScrollBg.value = `rgba(${from.r}, ${from.g}, ${from.b}, ${a})`;
    return;
  }

  const rect = el.getBoundingClientRect();
  const viewportMid = window.innerHeight / 2;
  const blockMidY = rect.top + rect.height / 2;
  const pixelsPastCenter = viewportMid - blockMidY;
  const range = landingBgBlendRangePx();
  const t =
    pixelsPastCenter <= 0 ? 0 : Math.min(1, pixelsPastCenter / range);

  const r = Math.round(from.r + (to.r - from.r) * t);
  const g = Math.round(from.g + (to.g - from.g) * t);
  const b = Math.round(from.b + (to.b - from.b) * t);
  landingScrollBg.value = `rgba(${r}, ${g}, ${b}, ${a})`;
}

function scheduleLandingScrollBackground() {
  if (landingScrollRafPending) return;
  landingScrollRafPending = true;
  landingScrollRaf = requestAnimationFrame(applyLandingScrollBackground);
}

onMounted(() => {
  requestAnimationFrame(() => applyLandingScrollBackground());
  window.addEventListener('scroll', scheduleLandingScrollBackground, { passive: true });
  window.addEventListener('resize', scheduleLandingScrollBackground, { passive: true });
});

onUnmounted(() => {
  window.removeEventListener('scroll', scheduleLandingScrollBackground);
  window.removeEventListener('resize', scheduleLandingScrollBackground);
  if (landingScrollRaf) cancelAnimationFrame(landingScrollRaf);
});

watch(isDark, () => {
  requestAnimationFrame(() => applyLandingScrollBackground());
});
</script>

<template>
  <div
    id="main-content"
    class="landing-page-root overflow-x-hidden text-foreground"
    :style="{ backgroundColor: landingScrollBg }"
    aria-labelledby="landing-hero-heading"
  >
    <!-- Hero -->
    <section
      class="relative flex min-h-[calc(100svh-3.5rem)] items-center justify-center overflow-hidden"
      aria-label="Главный экран"
    >
      <!-- LCP: осмысленное изображение вместо одного только background-image -->
      <picture class="pointer-events-none absolute inset-0 z-0 block h-full w-full">
        <source media="(max-width: 767px)" :srcset="HOME_HERO_MOBILE_IMAGE_PATH" />
        <img
          :src="HOME_HERO_IMAGE_PATH"
          :alt="heroImageAlt"
          width="1920"
          height="1080"
          sizes="100vw"
          fetchpriority="high"
          decoding="sync"
          class="h-full w-full object-cover object-center"
        />
      </picture>

      <div class="container relative z-10 w-full py-10 md:py-14">
        <div class="mx-auto flex max-w-4xl flex-col items-center gap-14 px-2 text-center sm:px-4">

          <h1
            id="landing-hero-heading"
            class="hero-eyebrow"
          >
            Управление гильдией в MMORPG — gg-hub
          </h1>

          <p
            class="hero-slogan text-3xl font-bold tracking-tight sm:text-4xl md:text-5xl lg:text-6xl"
          >
            <span class="hero-gradient-text">Твоя гильдия</span><br />
            <span class="hero-gradient-text-next">Твоя команда</span>
          </p>

          <p
            class="hero-lead-screen max-w-xl text-pretty text-lg md:text-xl"
          >
            {{ HOME_PAGE_LEAD }}
          </p>

          <div class="flex flex-wrap justify-center gap-3 sm:gap-4">
            <RouterLink
              to="/register"
              title="Регистрация на gg-hub"
              aria-label="Начать бесплатно — перейти к регистрации"
              class="landing-cta-btn landing-cta-btn--lead hero-btn inline-flex items-center justify-center rounded-md px-7 py-3 text-base font-semibold no-underline transition-[background-color,box-shadow,filter] duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a54a]/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:px-8"
              @click="void recordLandingCtaClick('start_free').catch(() => {})"
            >
              <span class="relative z-[1]">Начать бесплатно</span>
            </RouterLink>
            <RouterLink
              to="/guilds"
              title="Перейти к каталогу гильдий и инструментам управления гильдией"
              aria-label="Найти гильдию — каталог и инструменты управления гильдией"
              class="landing-cta-btn landing-cta-btn--muted landing-cta-btn--muted-hero inline-flex items-center justify-center rounded-md px-7 py-3 text-base font-medium no-underline transition-[transform,box-shadow,background-color,backdrop-filter] duration-300 hover:scale-[1.02] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a54a]/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:px-8"
            >
              Найти гильдию
            </RouterLink>
          </div>

          <!-- Scroll indicator -->
          <div class="hero-scroll-indicator mt-8 animate-bounce" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            <span>Узнать больше</span>
          </div>
        </div>
      </div>
    </section>

    <div class="landing-mid relative overflow-x-hidden">
      <div class="relative z-[1]">
        <div class="landing-bg-band landing-bg-band--1">
          <div class="landing-bg-edge landing-bg-edge--top" aria-hidden="true" />
    <section
      :ref="setRef('capabilities')"
      class="container py-[4.5rem] md:py-24"
      aria-labelledby="section-capabilities-heading"
    >
      <div
        :ref="setRef('capabilities-header')"
        data-reveal-id="capabilities-header"
        class="mx-auto max-w-3xl text-center transition-opacity duration-700"
        :class="show('capabilities-header') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="section-capabilities-heading" class="text-2xl font-bold tracking-tight text-pretty sm:text-3xl">
          {{ HOME_CAPABILITIES_HEADING }}
        </h2>
        <p class="mt-4 text-base text-muted-foreground leading-relaxed text-pretty md:text-lg">
          Платформа для лидеров, офицеров и игроков — основные модули в одном интерфейсе.
        </p>
      </div>
      <div
        :ref="setRef('capabilities-cards')"
        data-reveal-id="capabilities-cards"
        class="mx-auto mt-10 grid max-w-6xl gap-4 sm:grid-cols-2 lg:grid-cols-3"
      >
        <Card
          v-for="(item, i) in HOME_CAPABILITIES_ITEMS"
          :key="item.title"
          class="transition-opacity duration-500"
          :class="show('capabilities-cards') ? 'opacity-100' : 'opacity-0'"
          :style="{ transitionDelay: `${i * 80}ms` }"
        >
          <CardHeader>
            <CardTitle class="text-base">{{ item.title }}</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground leading-relaxed">{{ item.desc }}</p>
          </CardContent>
        </Card>
      </div>
    </section>

    <section
      :ref="setRef('seo-intro')"
      class="container py-12 md:py-16"
      aria-labelledby="seo-intro-heading"
    >
      <article
        class="mx-auto max-w-3xl transition-opacity duration-700"
        :class="show('seo-intro') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="seo-intro-heading" class="text-2xl font-bold tracking-tight text-pretty sm:text-3xl">
          {{ HOME_SEO_INTRO_HEADING }}
        </h2>
        <p
          v-for="(paragraph, idx) in HOME_SEO_INTRO_PARAGRAPHS"
          :key="idx"
          class="mt-4 text-base text-muted-foreground leading-relaxed text-pretty md:text-lg"
        >
          {{ paragraph }}
        </p>
        <p class="mt-6">
          <RouterLink
            to="/guilds"
            :title="HOME_SEO_INTRO_CTA_LABEL"
            class="inline-flex font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-sm"
          >
            {{ HOME_SEO_INTRO_CTA_LABEL }} →
          </RouterLink>
        </p>
      </article>
    </section>

    <!-- Games ticker -->
    <section
      :ref="setRef('games')"
      class="overflow-hidden"
      aria-labelledby="section-games-heading"
    >
      <div class="container py-10">
        <h2 id="section-games-heading" class="mx-auto mb-8 max-w-3xl text-center text-xl font-semibold tracking-tight text-pretty sm:text-2xl">
          Поддерживаемые MMORPG
        </h2>
        <div class="flex flex-wrap items-center justify-center gap-6 md:gap-12" role="list">
          <RouterLink
            v-for="(game, i) in games"
            :key="game.slug"
            :to="guildsLinkForGame(game)"
            role="listitem"
            class="text-lg font-semibold text-foreground/75 underline-offset-4 transition-opacity hover:text-foreground hover:scale-110 hover:underline md:text-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-sm"
            :class="show('games') ? 'opacity-100' : 'opacity-0'"
            :style="{ transitionDelay: `${200 + i * 150}ms`, transitionDuration: '600ms' }"
          >
            {{ game.name }}
          </RouterLink>
        </div>
        <p class="mx-auto mt-8 max-w-3xl text-pretty text-center text-base text-muted-foreground leading-relaxed md:text-lg">
          <strong class="font-semibold text-foreground">gg-hub</strong> — бесплатная платформа для
          <strong class="font-semibold text-foreground">ведения гильдии</strong>:
          ростер, заявки, календарь, рейды, хранилище, ДКП и блог в одном интерфейсе.
          Список игр расширяется в админке; выше — сообщества, которые уже ведут гильдии на платформе.
        </p>

        <ul
          class="mx-auto mt-10 grid max-w-3xl gap-4 sm:grid-cols-3"
          aria-label="Ключевые показатели платформы"
        >
          <li
            v-for="(item, i) in platformHighlights"
            :key="item.label"
            class="rounded-lg border border-border/80 bg-card/80 px-4 py-5 text-center backdrop-blur-sm transition-opacity duration-600"
            :class="show('games') ? 'opacity-100' : 'opacity-0'"
            :style="{ transitionDelay: `${300 + i * 100}ms` }"
          >
            <p class="text-2xl font-bold tracking-tight text-primary md:text-3xl">{{ item.value }}</p>
            <p class="mt-1 text-sm text-muted-foreground leading-snug">{{ item.label }}</p>
          </li>
        </ul>
      </div>
    </section>

        </div>
        <div class="landing-bg-band landing-bg-band--2">
    <!-- Player Benefits -->
    <section class="container relative py-16 md:py-24" aria-labelledby="section-players-heading">
      <div
        :ref="playersHeaderRef"
        data-reveal-id="players-header"
        class="mx-auto max-w-3xl text-center transition-opacity duration-700"
        :class="show('players-header') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="section-players-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Для игроков
        </h2>
        <p class="mt-4 text-lg text-muted-foreground text-pretty">
          Каталог гильдий, заявки с анкетой, персонажи в разных играх и лента новостей — без бесконечного поиска по Discord и форумам.
        </p>
      </div>

      <div
        :ref="setRef('players-cards')"
        data-reveal-id="players-cards"
        class="mx-auto mt-12 grid max-w-5xl gap-6 sm:grid-cols-2"
      >
        <Card
          v-for="(b, i) in playerBenefits"
          :key="b.title"
          class="group cursor-default transition-opacity duration-500 hover:shadow-lg hover:-translate-y-1"
          :class="show('players-cards') ? 'opacity-100' : 'opacity-0'"
          :style="{
            transitionDelay: `${i * 120}ms`,
          }"
        >
          <CardHeader class="flex-row items-start gap-4">
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-primary-foreground group-hover:scale-110 group-hover:rotate-3 group-hover:shadow-md"
              aria-hidden="true"
              v-html="b.icon"
            />
            <div>
              <CardTitle class="text-lg transition-colors duration-300 group-hover:text-primary">{{ b.title }}</CardTitle>
              <p class="mt-1.5 text-sm text-muted-foreground leading-relaxed">{{ b.desc }}</p>
            </div>
          </CardHeader>
        </Card>
      </div>
    </section>

        </div>
        <div class="landing-bg-band landing-bg-band--3">
    <!-- Guild Benefits -->
    <section class="container py-16 md:py-24" aria-labelledby="section-guilds-heading">
      <div
        :ref="setRef('guild-header')"
        data-reveal-id="guild-header"
        class="mx-auto max-w-3xl text-center transition-opacity duration-700"
        :class="show('guild-header') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="section-guilds-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Для лидеров и офицеров
        </h2>
        <p class="mt-4 text-lg text-muted-foreground text-pretty">
          Лидеры и офицеры ведут состав, заявки, рейды, хранилище и ДКП — каждый в рамках выданных прав, без таблиц и хаоса в каналах.
        </p>
      </div>

      <ul
        :ref="setRef('role-scenarios')"
        data-reveal-id="role-scenarios"
        class="mx-auto mt-8 flex max-w-4xl flex-wrap justify-center gap-2 transition-opacity duration-700 sm:gap-3"
        :class="show('role-scenarios') ? 'opacity-100' : 'opacity-0'"
        aria-label="Роли в гильдии на gg-hub"
      >
        <li
          v-for="(s, i) in roleScenarios"
          :key="s.role"
          class="rounded-full border border-border/80 bg-card/80 px-3 py-1.5 text-sm backdrop-blur-sm sm:px-4"
          :style="{ transitionDelay: `${i * 60}ms` }"
        >
          <span class="font-medium text-foreground">{{ s.role }}:</span>
          <span class="text-muted-foreground"> {{ s.text }}</span>
        </li>
      </ul>

      <div
        :ref="setRef('guild-cards')"
        data-reveal-id="guild-cards"
        class="mx-auto mt-12 grid max-w-6xl gap-6 sm:grid-cols-2 lg:grid-cols-3"
      >
        <Card
          v-for="(b, i) in guildBenefits"
          :key="b.title"
          class="group cursor-default transition-opacity duration-500 hover:shadow-lg hover:-translate-y-1"
          :class="show('guild-cards') ? 'opacity-100' : 'opacity-0'"
          :style="{ transitionDelay: `${i * 120}ms` }"
        >
          <CardHeader class="flex-row items-start gap-4">
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover:bg-primary group-hover:text-primary-foreground group-hover:scale-110 group-hover:-rotate-3 group-hover:shadow-md"
              aria-hidden="true"
              v-html="b.icon"
            />
            <div>
              <CardTitle class="text-lg transition-colors duration-300 group-hover:text-primary">{{ b.title }}</CardTitle>
              <p class="mt-1.5 text-sm text-muted-foreground leading-relaxed">{{ b.desc }}</p>
            </div>
          </CardHeader>
        </Card>
      </div>
    </section>

        </div>
        <div class="landing-bg-band landing-bg-band--4">
    <!-- How it works -->
    <section class="container py-16 md:py-24" aria-labelledby="section-steps-heading">
      <div
        :ref="setRef('steps-header')"
        data-reveal-id="steps-header"
        class="mx-auto max-w-3xl text-center transition-opacity duration-700"
        :class="show('steps-header') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="section-steps-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Как работает gg-hub
        </h2>
        <p class="mt-4 text-lg text-muted-foreground text-pretty">
          От регистрации до ведения рейдов, хранилища и ДКП — четыре понятных шага.
        </p>
      </div>

      <div
        :ref="setRef('steps')"
        data-reveal-id="steps"
        class="mx-auto mt-12 grid max-w-5xl gap-8 sm:grid-cols-2 lg:grid-cols-4"
      >
        <div
          v-for="(step, i) in steps"
          :key="step.num"
          class="group relative text-center transition-opacity duration-600"
          :class="show('steps') ? 'opacity-100' : 'opacity-0'"
          :style="{ transitionDelay: `${i * 200}ms` }"
        >
          <div class="landing-step-badge relative mx-auto flex h-16 w-16 items-center justify-center rounded-full text-xl font-bold transition-all duration-500 group-hover:scale-105">
            <span class="relative z-[1]">{{ step.num }}</span>
            <div class="landing-step-badge__glow absolute inset-0 rounded-full opacity-0 group-hover:opacity-100" style="animation-duration: 1.5s" />
          </div>
          <!-- Connector line (hidden on mobile) -->
          <div
            v-if="i < steps.length - 1"
            class="absolute top-8 left-[calc(50%+2rem)] hidden h-px w-[calc(100%-4rem)] bg-border transition-opacity md:block"
            :class="show('steps') ? 'opacity-100' : 'opacity-0'"
            :style="{ transitionDelay: `${400 + i * 200}ms`, transitionDuration: '800ms' }"
          />
          <h3 class="mt-5 text-lg font-semibold transition-colors duration-300 group-hover:text-primary">{{ step.title }}</h3>
          <p class="mt-2 text-sm text-muted-foreground leading-relaxed text-pretty">{{ step.desc }}</p>
          <RouterLink
            v-if="step.link"
            :to="step.link.to"
            class="mt-3 inline-flex text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-sm"
          >
            {{ step.link.label }} →
          </RouterLink>
        </div>
      </div>
    </section>

        </div>
        <div class="landing-bg-band landing-bg-band--5">
    <!-- Features grid -->
    <section class="container py-16 md:py-24" aria-labelledby="section-features-heading">
      <div
        :ref="setRef('features-header')"
        data-reveal-id="features-header"
        class="mx-auto max-w-3xl text-center transition-opacity duration-700"
        :class="show('features-header') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="section-features-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Инструменты для лидера и офицеров
        </h2>
        <p class="mt-4 text-lg text-muted-foreground text-pretty">
          Модули gg-hub, которые закрывают ежедневные задачи лидера и офицеров — подробности в разделе гильдии после входа.
        </p>
      </div>

      <div
        :ref="setRef('features')"
        data-reveal-id="features"
        class="mx-auto mt-12 grid max-w-5xl gap-4 sm:grid-cols-2 lg:grid-cols-3"
      >
        <Card
          v-for="(f, i) in features"
          :key="f.title"
          class="group cursor-default overflow-hidden transition-opacity duration-500 hover:shadow-lg hover:-translate-y-1"
          :class="show('features') ? 'opacity-100' : 'opacity-0'"
          :style="{ transitionDelay: `${i * 100}ms` }"
        >
          <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-transparent via-primary to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
          <CardHeader>
            <CardTitle class="text-base transition-colors duration-300 group-hover:text-primary">{{ f.title }}</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground leading-relaxed">{{ f.desc }}</p>
          </CardContent>
        </Card>
      </div>
    </section>

    <!-- Почему не Discord -->
        </div>
        <div class="landing-bg-band landing-bg-band--6">
    <section
      :ref="setRef('discord-block')"
      class="container py-[4.5rem] md:py-48"
      aria-labelledby="section-discord-heading"
    >
      <article
        class="mx-auto max-w-3xl transition-opacity duration-700"
        :class="show('discord-block') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="section-discord-heading" class="text-2xl font-bold tracking-tight text-pretty sm:text-3xl">
          {{ HOME_DISCORD_BLOCK_HEADING }}
        </h2>
        <p
          v-for="(paragraph, idx) in HOME_DISCORD_BLOCK_PARAGRAPHS"
          :key="idx"
          class="mt-4 text-base text-muted-foreground leading-relaxed text-pretty md:text-lg"
        >
          {{ paragraph }}
        </p>
      </article>
    </section>

    <!-- FAQ -->
        </div>
        <div class="landing-bg-band landing-bg-band--7">
    <section
      class="container py-16 md:py-24"
      aria-labelledby="section-faq-heading"
      itemscope
      itemtype="https://schema.org/FAQPage"
    >
      <div
        :ref="setRef('faq-header')"
        data-reveal-id="faq-header"
        class="mx-auto max-w-3xl text-center transition-opacity duration-700"
        :class="show('faq-header') ? 'opacity-100' : 'opacity-0'"
      >
        <h2 id="section-faq-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          {{ HOME_FAQ_SECTION_HEADING }}
        </h2>
        <p class="mt-4 text-lg text-muted-foreground text-pretty">
          Ответы о платформе gg-hub, регистрации, играх, ДКП и отличии от Discord.
        </p>
      </div>

      <div
        :ref="setRef('faq-list')"
        data-reveal-id="faq-list"
        class="mx-auto mt-10 flex max-w-3xl flex-col gap-3"
      >
        <details
          v-for="(item, i) in HOME_FAQ_ITEMS"
          :key="item.question"
          class="landing-faq-item group transition-opacity duration-500"
          :class="show('faq-list') ? 'opacity-100' : 'opacity-0'"
          :style="{ transitionDelay: `${i * 80}ms` }"
          itemscope
          itemprop="mainEntity"
          itemtype="https://schema.org/Question"
        >
          <summary class="landing-faq-summary">
            <span class="landing-faq-question" itemprop="name">{{ item.question }}</span>
            <svg
              class="landing-faq-chevron"
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="m6 9 6 6 6-6" />
            </svg>
          </summary>
          <div
            class="landing-faq-answer"
            itemscope
            itemprop="acceptedAnswer"
            itemtype="https://schema.org/Answer"
          >
            <p itemprop="text">{{ item.answer }}</p>
          </div>
        </details>
      </div>
    </section>
          <div class="landing-bg-edge landing-bg-edge--bottom" aria-hidden="true" />
        </div>
      </div>
    </div>

    <!-- CTA -->
    <section
      class="landing-cta-section relative flex min-h-[min(92vh,56rem)] items-end justify-center overflow-hidden"
      aria-label="Регистрация"
    >
      <img
        :src="homeCtaImagePath"
        :alt="homeCtaImageAlt"
        width="1920"
        height="1080"
        loading="lazy"
        decoding="async"
        class="pointer-events-none absolute inset-0 z-0 h-full w-full object-cover object-center"
        aria-hidden="true"
      />
      <div class="landing-cta-scrim" aria-hidden="true" />
      <div
        :ref="setRef('cta')"
        data-reveal-id="cta"
        class="landing-cta-solid relative z-10 w-full"
      >
        <div class="container pb-16 pt-28 md:pb-20 md:pt-36">
          <div
            class="mx-auto flex max-w-3xl flex-col items-center gap-5 text-center transition-opacity duration-700 md:gap-6"
            :class="show('cta') ? 'opacity-100' : 'opacity-0'"
          >
            <h2 class="landing-cta-title text-pretty px-2 text-2xl leading-tight sm:text-3xl md:text-4xl lg:text-[2.75rem]">
              Готов найти свою команду?
            </h2>
            <p class="landing-cta-lead max-w-2xl px-2 text-base leading-relaxed text-white/95 md:text-lg text-pretty">
              Бесплатная платформа для MMORPG-сообществ: зарегистрируйся, создай гильдию или подай заявку в каталоге — и веди состав на gg-hub.ru.
            </p>
            <div class="mt-2 flex flex-wrap justify-center gap-3 sm:gap-4">
              <RouterLink
                to="/register"
                class="landing-cta-btn landing-cta-btn--lead hero-btn inline-flex items-center justify-center rounded-md px-7 py-3 text-base font-semibold no-underline transition-[background-color,box-shadow,filter] duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a54a]/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:px-8"
              >
                <span class="relative z-[1]">Создать гильдию бесплатно</span>
              </RouterLink>
              <RouterLink
                to="/guilds"
                title="Каталог гильдий MMORPG и инструменты управления гильдией"
                aria-label="Смотреть гильдии — каталог и инструменты управления гильдией"
                class="landing-cta-btn landing-cta-btn--muted inline-flex items-center justify-center rounded-md px-7 py-3 text-base font-medium no-underline transition-[transform,box-shadow,background-color] duration-300 hover:scale-[1.02] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a54a]/60 focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:px-8"
              >
                Смотреть гильдии
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </section>

    <ClientOnly>
      <LandingDevModal
        v-if="devModalOpen"
        :open="devModalOpen"
        @close="closeLandingCtaModal"
        @update:open="(v: boolean) => { if (!v) closeLandingCtaModal(); }"
      />
    </ClientOnly>
  </div>
</template>

<style scoped>
/* Плавное «стекло» снизу: маска гасит эффект к верху и по краям */
.hero-content-scrim {
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background: linear-gradient(
    to top,
    color-mix(in oklch, var(--background) 78%, transparent) 0%,
    color-mix(in oklch, var(--background) 42%, transparent) 32%,
    color-mix(in oklch, var(--background) 14%, transparent) 52%,
    transparent 72%
  );
  backdrop-filter: blur(16px) saturate(1.15);
  -webkit-backdrop-filter: blur(16px) saturate(1.15);
  mask-image: radial-gradient(
    125% 85% at 50% 100%,
    #000 0%,
    #000 28%,
    rgba(0, 0, 0, 0.55) 48%,
    rgba(0, 0, 0, 0.18) 62%,
    transparent 76%
  );
  -webkit-mask-image: radial-gradient(
    125% 85% at 50% 100%,
    #000 0%,
    #000 28%,
    rgba(0, 0, 0, 0.55) 48%,
    rgba(0, 0, 0, 0.18) 62%,
    transparent 76%
  );
}

.hero-text-readable {
  text-shadow: 0 1px 2px hsl(0 0% 0% / 0.14), 0 2px 24px hsl(0 0% 0% / 0.12);
}

.hero-lead-screen {
  margin: 0 auto;
  color: rgba(255, 255, 255, 0.94);
  font-weight: 400;
  line-height: 1.58;
  text-align: center;
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.62),
    0 2px 18px rgba(0, 0, 0, 0.45),
    0 0 34px rgba(0, 0, 0, 0.32);
}

@media (min-width: 768px) {
  .hero-lead-screen {
    line-height: 1.55;
  }
}

.hero-scroll-indicator {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
  color: rgba(255, 255, 255, 0.92);
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.62),
    0 2px 14px rgba(0, 0, 0, 0.42);
}

.hero-scroll-indicator svg {
  display: block;
}

.hero-scroll-indicator span {
  font-size: 0.82rem;
  font-weight: 400;
  line-height: 1.2;
}

/* Эйбрау над слоганом: несёт ключевой запрос, не перетягивает визуал. */
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.9rem;
  margin: 0;
  border: 1px solid rgba(201, 165, 74, 0.55);
  border-radius: 9999px;
  background: rgba(12, 10, 8, 0.42);
  backdrop-filter: blur(6px) saturate(1.05);
  -webkit-backdrop-filter: blur(6px) saturate(1.05);
  color: #f5e3b4;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  line-height: 1.2;
  text-align: center;
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.55),
    0 0 14px rgba(0, 0, 0, 0.35);
  box-shadow:
    0 2px 12px rgba(0, 0, 0, 0.25),
    inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

@media (max-width: 740px) {
  .hero-eyebrow {
    font-size: 0.62rem;
  }
}

@media (min-width: 640px) {
  .hero-eyebrow {
    font-size: 0.78rem;
    padding: 0.4rem 1.05rem;
    letter-spacing: 0.2em;
  }
}

@media (min-width: 768px) {
  .hero-eyebrow {
    font-size: 0.82rem;
    letter-spacing: 0.22em;
  }
}

/* Лёгкая стеклянная подложка: радиальный градиент, без рамки и кромок */
.hero-lead-glass {
  position: relative;
  margin-inline: auto;
  padding: 1rem 1.25rem;
  border: none;
  border-radius: 60px;
  /*background: radial-gradient(
    ellipse 95% 120% at 50% 42%,
    color-mix(in oklch, white 12%, transparent) 0%,
    color-mix(in oklch, var(--background) 90%, transparent) 38%,
    color-mix(in oklch, var(--primary) 4%, transparent) 55%,
    transparent 72%
  );*/
  background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0) 90%, rgba(255, 255, 255, 0) 100%);
  backdrop-filter: blur(2px) saturate(1.05);
  -webkit-backdrop-filter: blur(6px) saturate(1.05);
  box-shadow: none;
}

@media (min-width: 768px) {
  .hero-lead-glass {
    padding: 1.125rem 1.75rem;
  }
}

.hero-gradient-text,
.hero-gradient-text-next {
  background-size: 200% auto;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: none;
  filter:
    drop-shadow(0 2px 10px hsl(0 0% 0% / 0.35))
    drop-shadow(0 1px 2px hsl(0 0% 0% / 0.25));
}

.hero-gradient-text {
  background-image: linear-gradient(180deg, #ffffff 0%, #f9f5eb 58%, #ddd3bf 100%);
  animation: none;
}

.hero-gradient-text-next {
  background-image: linear-gradient(180deg, #fff4c9 0%, #f0cf69 42%, #c99a2e 72%, #f4d97b 100%);
  background-size: 100% 145%;
  background-position: center 34%;
  animation: none;
  filter:
    drop-shadow(0 3px 12px hsl(0 0% 0% / 0.42))
    drop-shadow(0 0 20px hsl(43 70% 45% / 0.26));
}

@keyframes gradient-shift {
  0%, 100% { background-position: 0% center; }
  50% { background-position: 100% center; }
}

.hero-btn {
  position: relative;
  overflow: hidden;
  isolation: isolate;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hero-btn:hover {
  transform: scale(1.05);
  box-shadow: 0 8px 30px -8px color-mix(in oklch, var(--primary) 40%, transparent);
}
.hero-btn::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background: linear-gradient(105deg, transparent 40%, rgba(255, 255, 255, 0.15) 45%, rgba(255, 255, 255, 0.25) 50%, rgba(255, 255, 255, 0.15) 55%, transparent 60%);
  background-size: 300% 100%;
  animation: shimmer 3s ease-in-out infinite;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@keyframes float {
  0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.4; }
  25% { transform: translateY(-20px) rotate(3deg); opacity: 0.7; }
  50% { transform: translateY(-35px) rotate(-2deg); opacity: 0.5; }
  75% { transform: translateY(-15px) rotate(1deg); opacity: 0.8; }
}

@keyframes float-slow {
  0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.3; }
  33% { transform: translateY(-25px) rotate(-3deg); opacity: 0.6; }
  66% { transform: translateY(-10px) rotate(2deg); opacity: 0.4; }
}

.animate-float {
  animation: float 6s ease-in-out infinite;
}
.animate-float-slow {
  animation: float-slow 9s ease-in-out infinite;
}

/* --- Средняя часть лендинга: фоновые изображения (между hero и нижним CTA) --- */
.landing-bg-band {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  color: rgba(255, 255, 255, 0.95);
}

.landing-bg-band :is(h2, h3, strong, .text-foreground) {
  color: rgba(255, 255, 255, 0.98) !important;
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.72),
    0 0 22px rgba(0, 0, 0, 0.42);
}

.landing-bg-band :is(p, .text-muted-foreground) {
  color: rgba(235, 239, 247, 0.88) !important;
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.68),
    0 0 18px rgba(0, 0, 0, 0.35);
}

.landing-bg-band a {
  color: #f4d97b !important;
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.72),
    0 0 16px rgba(0, 0, 0, 0.42);
}

.landing-bg-band :deep([class*="bg-card"]) {
  border-color: rgba(242, 217, 160, 0.28) !important;
  background-color: rgba(14, 17, 24, 0.74) !important;
  color: rgba(255, 255, 255, 0.96) !important;
  backdrop-filter: blur(10px) saturate(1.12);
  -webkit-backdrop-filter: blur(10px) saturate(1.12);
  box-shadow:
    0 14px 36px rgba(0, 0, 0, 0.28),
    inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.landing-bg-band :deep([class*="bg-primary/10"]) {
  background-color: rgba(244, 217, 123, 0.16) !important;
  color: #f4d97b !important;
}

.landing-bg-band :deep([class*="bg-primary"]) {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.24);
}

.landing-bg-band::before {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -2;
  background-image: var(--landing-band-bg);
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
}

.landing-bg-band::after {
  content: "";
  position: absolute;
  inset: 0;
  z-index: -1;
  background:
    linear-gradient(180deg, rgba(8, 10, 14, 0.36) 0%, rgba(8, 10, 14, 0.18) 48%, rgba(8, 10, 14, 0.42) 100%),
    radial-gradient(ellipse 70% 50% at 50% 20%, rgba(255, 248, 230, 0.08), transparent 72%);
  backdrop-filter: none;
}

.dark .landing-bg-band::after {
  background:
    linear-gradient(180deg, rgba(10, 12, 16, 0.72) 0%, rgba(10, 12, 16, 0.56) 48%, rgba(10, 12, 16, 0.76) 100%),
    radial-gradient(ellipse 70% 50% at 50% 20%, rgba(80, 100, 140, 0.22), transparent 70%);
}

.landing-bg-band + .landing-bg-band::after {
  background:
    linear-gradient(
      90deg,
      transparent 0%,
      rgba(244, 217, 123, 0.36) 20%,
      rgba(125, 190, 255, 0.32) 45%,
      rgba(215, 120, 255, 0.24) 66%,
      rgba(244, 217, 123, 0.36) 84%,
      transparent 100%
    ) top center / 220% 1px no-repeat,
    radial-gradient(ellipse 76% 24px at 50% 0, rgba(244, 217, 123, 0.1), rgba(125, 190, 255, 0.07) 46%, transparent 74%) top center / 100% 42px no-repeat,
    linear-gradient(180deg, rgba(8, 10, 14, 0.42) 0%, rgba(8, 10, 14, 0.2) 48%, rgba(8, 10, 14, 0.44) 100%),
    radial-gradient(ellipse 70% 50% at 50% 20%, rgba(255, 248, 230, 0.08), transparent 72%);
  animation: landing-bg-seam-flow 10s ease-in-out infinite;
}

.landing-bg-edge {
  position: absolute;
  left: 0;
  right: 0;
  z-index: 0;
  height: 42px;
  pointer-events: none;
  animation: landing-bg-seam-flow 10s ease-in-out infinite;
}

.landing-bg-edge--top {
  top: 0;
  background:
    linear-gradient(
      90deg,
      transparent 0%,
      rgba(244, 217, 123, 0.36) 20%,
      rgba(125, 190, 255, 0.32) 45%,
      rgba(215, 120, 255, 0.24) 66%,
      rgba(244, 217, 123, 0.36) 84%,
      transparent 100%
    ) top center / 220% 1px no-repeat,
    radial-gradient(ellipse 76% 24px at 50% 0, rgba(244, 217, 123, 0.1), rgba(125, 190, 255, 0.07) 46%, transparent 74%) top center / 100% 42px no-repeat;
}

.landing-bg-edge--bottom {
  bottom: 0;
  background:
    linear-gradient(
      90deg,
      transparent 0%,
      rgba(244, 217, 123, 0.36) 20%,
      rgba(125, 190, 255, 0.32) 45%,
      rgba(215, 120, 255, 0.24) 66%,
      rgba(244, 217, 123, 0.36) 84%,
      transparent 100%
    ) bottom center / 220% 1px no-repeat,
    radial-gradient(ellipse 76% 24px at 50% 100%, rgba(244, 217, 123, 0.1), rgba(125, 190, 255, 0.07) 46%, transparent 74%) bottom center / 100% 42px no-repeat;
}
.landing-bg-edge--top {
  animation-name: landing-bg-edge-top-flow;
}

.landing-bg-edge--bottom {
  animation-name: landing-bg-edge-bottom-flow;
}

@keyframes landing-bg-edge-top-flow {
  0%,
  100% {
    background-position: 0% 0, center top;
  }
  50% {
    background-position: 100% 0, center top;
  }
}

@keyframes landing-bg-edge-bottom-flow {
  0%,
  100% {
    background-position: 0% 100%, center bottom;
  }
  50% {
    background-position: 100% 100%, center bottom;
  }
}
@keyframes landing-bg-seam-flow {
  0%,
  100% {
    background-position: 0% 0, center top, center, center;
  }
  50% {
    background-position: 100% 0, center top, center, center;
  }
}

.landing-bg-band--1 { --landing-band-bg: url("/assets/images/bg_1.webp"); }
.landing-bg-band--2 { --landing-band-bg: url("/assets/images/bg_2.webp"); }
.landing-bg-band--3 { --landing-band-bg: url("/assets/images/bg_3.webp"); }
.landing-bg-band--4 { --landing-band-bg: url("/assets/images/bg_4.webp"); }
.landing-bg-band--5 { --landing-band-bg: url("/assets/images/bg_5.webp"); }
.landing-bg-band--6 { --landing-band-bg: url("/assets/images/bg_6.webp"); }
.landing-bg-band--7 { --landing-band-bg: url("/assets/images/bg_7.webp"); }

/* --- Steps: fantasy badge --- */
.landing-step-badge {
  isolation: isolate;
  border: 1px solid rgba(244, 217, 123, 0.42);
  background:
    radial-gradient(circle at 35% 25%, rgba(255, 248, 220, 0.18), transparent 38%),
    linear-gradient(145deg, rgba(28, 31, 39, 0.94) 0%, rgba(10, 12, 18, 0.94) 58%, rgba(42, 31, 18, 0.9) 100%);
  color: #f4d97b;
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.85),
    0 0 14px rgba(244, 217, 123, 0.34);
  box-shadow:
    0 12px 28px rgba(0, 0, 0, 0.36),
    0 0 0 4px rgba(244, 217, 123, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.14),
    inset 0 -10px 18px rgba(0, 0, 0, 0.28);
}

.landing-step-badge__glow {
  z-index: 0;
  background: radial-gradient(circle, rgba(244, 217, 123, 0.18), rgba(125, 190, 255, 0.08) 46%, transparent 72%);
  filter: blur(8px);
  transform: scale(1.2);
  transition: opacity 0.35s ease;
}

.group:hover .landing-step-badge {
  border-color: rgba(244, 217, 123, 0.36);
  color: #ffe8a6;
  box-shadow:
    0 16px 34px rgba(0, 0, 0, 0.42),
    0 0 0 4px rgba(244, 217, 123, 0.12),
    0 0 28px rgba(244, 217, 123, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.18),
    inset 0 -10px 18px rgba(0, 0, 0, 0.3);
}
/* --- FAQ: управление гильдией --- */
.landing-faq-item {
  border: 1px solid color-mix(in oklch, var(--primary) 22%, var(--border));
  border-radius: 0.75rem;
  background: color-mix(in oklch, var(--card) 96%, transparent);
  box-shadow: 0 1px 2px hsl(0 0% 0% / 0.04);
  transition: box-shadow 0.3s ease, border-color 0.3s ease, transform 0.3s ease;
}

.landing-faq-item:hover {
  border-color: color-mix(in oklch, var(--primary) 42%, var(--border));
  box-shadow: 0 4px 18px hsl(0 0% 0% / 0.08);
}

.landing-faq-item[open] {
  border-color: color-mix(in oklch, var(--primary) 48%, var(--border));
  box-shadow: 0 6px 22px hsl(0 0% 0% / 0.08);
}

.landing-faq-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  cursor: pointer;
  list-style: none;
  font-weight: 600;
  color: var(--foreground);
  transition: color 0.25s ease;
}

.landing-faq-summary::-webkit-details-marker {
  display: none;
}

.landing-faq-summary:hover {
  color: var(--primary);
}

.landing-faq-summary:focus-visible {
  outline: 2px solid color-mix(in oklch, var(--primary) 60%, transparent);
  outline-offset: 2px;
  border-radius: 0.5rem;
}

.landing-faq-question {
  flex: 1;
  min-width: 0;
  font-size: 1rem;
  line-height: 1.5;
}

@media (min-width: 768px) {
  .landing-faq-question {
    font-size: 1.0625rem;
  }
}

.landing-faq-chevron {
  flex-shrink: 0;
  color: color-mix(in oklch, var(--primary) 75%, var(--muted-foreground));
  transition: transform 0.3s ease;
}

.landing-faq-item[open] .landing-faq-chevron {
  transform: rotate(180deg);
}

.landing-faq-answer {
  padding: 0 1.25rem 1.1rem;
  color: var(--muted-foreground);
  font-size: 0.9375rem;
  line-height: 1.65;
  animation: landing-faq-answer-in 0.32s ease-out;
}

.landing-faq-answer p {
  margin: 0;
}

.landing-bg-band .landing-faq-summary,
.landing-bg-band .landing-faq-question {
  color: rgba(17, 24, 39, 0.96) !important;
  text-shadow: none !important;
}

.landing-bg-band .landing-faq-answer,
.landing-bg-band .landing-faq-answer p {
  color: rgba(45, 55, 72, 0.86) !important;
  text-shadow: none !important;
}

.dark .landing-bg-band .landing-faq-summary,
.dark .landing-bg-band .landing-faq-question {
  color: rgba(255, 255, 255, 0.96) !important;
}

.dark .landing-bg-band .landing-faq-answer,
.dark .landing-bg-band .landing-faq-answer p {
  color: rgba(235, 239, 247, 0.84) !important;
}
@keyframes landing-faq-answer-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .landing-faq-item,
  .landing-faq-chevron,
  .landing-faq-answer {
    transition: none;
    animation: none;
  }
}

/* --- Нижний CTA (как на макете) --- */
.landing-cta-scrim {
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background: linear-gradient(
    to bottom,
    hsl(220 18% 8% / 0) 0%,
    hsl(220 16% 6% / 0.18) 34%,
    hsl(220 14% 5% / 0.62) 56%,
    hsl(220 12% 4% / 0.92) 76%,
    hsl(220 10% 3.5% / 1) 100%
  );
}

.landing-cta-solid {
  position: relative;
  z-index: 2;
}

.landing-cta-title {
  font-weight: 600;
  text-transform: uppercase;
  background: linear-gradient(180deg, #f5ebd4 0%, #d4af37 38%, #9a7428 72%, #c9a54a 100%);
  background-size: 100% 140%;
  background-position: center 30%;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  filter: drop-shadow(0 2px 14px hsl(0 0% 0% / 0.45)) drop-shadow(0 0 24px hsl(43 45% 35% / 0.2));
}

.landing-cta-btn {
  min-height: 2.75rem;
  border-width: 1px;
  border-color: #c9a54a;
  color: #e8cf8a;
  text-shadow: 0 1px 2px hsl(0 0% 0% / 0.35);
}

.landing-cta-btn--outline {
  background-color: transparent;
}

.landing-cta-btn--outline:hover {
  background-color: hsl(43 35% 40% / 0.12);
  box-shadow: 0 0 0 1px hsl(43 50% 55% / 0.35);
}

/* Главный призыв: заметнее вторичной кнопки */
.landing-cta-btn--lead {
  border-width: 2px;
  border-color: #f2d9a0;
  background: linear-gradient(165deg, #d4a82e 0%, #a67a1e 45%, #7d5c12 100%);
  color: #fff8ec;
  font-weight: 600;
  text-shadow:
    0 1px 2px rgba(0, 0, 0, 0.45),
    0 0 1px rgba(0, 0, 0, 0.35);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.28),
    0 2px 16px rgba(0, 0, 0, 0.22),
    0 0 28px rgba(212, 168, 46, 0.45);
}

.landing-cta-btn--lead:hover {
  border-color: #ffe8bc;
  background: linear-gradient(165deg, #e4b83a 0%, #b88a26 45%, #8f6c16 100%);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.35),
    0 4px 22px rgba(0, 0, 0, 0.28),
    0 0 36px rgba(230, 185, 70, 0.55);
  filter: brightness(1.03);
}

.landing-cta-btn--muted {
  background-color: hsl(0 0% 100% / 0.08);
  border: 1px solid #c9a54a;
  color: #e8cf8a;
}

.landing-cta-btn--muted:hover {
  background-color: hsl(0 0% 100% / 0.14);
}

/* Вторичная кнопка на фоне hero-картинки: читаемость */
.landing-cta-btn--muted-hero {
  border-width: 2px;
  border-color: rgba(255, 236, 200, 0.85);
  color: #fff8ec;
  background-color: rgba(12, 10, 8, 0.58);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  text-shadow:
    0 1px 3px rgba(0, 0, 0, 0.65),
    0 0 12px rgba(0, 0, 0, 0.35);
  box-shadow:
    0 2px 14px rgba(0, 0, 0, 0.35),
    inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.landing-cta-btn--muted-hero:hover {
  background-color: rgba(12, 10, 8, 0.72);
  border-color: #ffecc8;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.18);
}

</style>

<style>
/* Тёмная тема: лендинг (класс .dark на корне документа) */
.dark .landing-page-root .hero-lead-glass {
  background: radial-gradient(
    circle,
    rgba(255, 255, 255, 0.07) 0%,
    rgba(255, 255, 255, 0) 88%,
    rgba(255, 255, 255, 0) 100%
  );
}

.dark .landing-page-root .hero-text-readable {
  text-shadow:
    0 1px 3px hsl(0 0% 0% / 0.75),
    0 2px 28px hsl(0 0% 0% / 0.55);
}

/*
 * Градиентный текст + filter на одном элементе ломает background-clip: text в Chromium
 * (видны «прямоугольники» вместо букв). Тень переносим на h1, на span — filter: none.
 */
.dark .landing-page-root .hero-slogan {
  filter: drop-shadow(0 2px 18px hsl(0 0% 0% / 0.88)) drop-shadow(0 1px 3px hsl(0 0% 0% / 0.65));
}

.dark .landing-page-root .hero-gradient-text,
.dark .landing-page-root .hero-gradient-text-next {
  filter: none;
  /* hex вместо oklch — стабильнее для clip-text в разных движках */
  background: linear-gradient(135deg, #ffffff 0%, #fff4dc 38%, #e8c078 72%, #c9a227 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  color: transparent;
}

</style>
