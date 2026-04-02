<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Separator } from '@/shared/ui';
import { RouterLink } from 'vue-router';
import { usePageSeo, getSiteOrigin } from '@/shared/lib/usePageSeo';

const siteOrigin = getSiteOrigin();
const canonicalUrl = `${siteOrigin}/`;
const seoDescription =
  'gg-hub — бесплатная платформа для игроков MMORPG и гильдий: поиск команды, Throne and Liberty, Aion 2, Black Desert, заявки в гильдию, рейды, календарь событий, ростер, блог и голосования. Русскоязычное сообщество.';
const seoKeywords =
  'гильдия MMORPG, найти гильдию, поиск гильдии, Throne and Liberty гильдия, Aion 2 гильдия, Black Desert гильдия, рекрутинг гильдии, клан MMORPG, рейды MMORPG, календарь ивентов, заявка в гильдию, gg-hub';

usePageSeo({
  title: 'gg-hub — гильдии MMORPG | Throne and Liberty, Aion 2, Black Desert',
  description: seoDescription,
  canonicalUrl,
  keywords: seoKeywords,
  ogImageUrl: (import.meta.env.VITE_OG_IMAGE_URL as string | undefined) || undefined,
  jsonLd: [
    {
      '@type': 'WebSite',
      '@id': `${siteOrigin}/#website`,
      name: 'gg-hub',
      url: canonicalUrl,
      description: seoDescription,
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
    },
    {
      '@type': 'Organization',
      '@id': `${siteOrigin}/#organization`,
      name: 'gg-hub',
      url: canonicalUrl,
      description: 'Платформа для организации игровых гильдий и игроков MMORPG.',
      logo: `${siteOrigin}/favicon.ico`,
    },
  ],
});

const games = [
  { name: 'Throne and Liberty', slug: 'throne-and-liberty' },
  { name: 'Aion 2', slug: 'aion-2' },
  { name: 'Black Desert', slug: 'black-desert' },
];

const playerBenefits = [
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>`,
    title: 'Найди свою гильдию',
    desc: 'Продвинутые фильтры по игре, серверу, классу, стилю игры и расписанию. Никаких случайных инвайтов — только осознанный выбор.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
    title: 'Единый профиль игрока',
    desc: 'Все твои персонажи из разных игр — в одном месте. История гильдий, достижения, классы и серверы.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>`,
    title: 'Будь в курсе',
    desc: 'Лента новостей, анонсы рейдов и ивентов, оповещения — ничего не пропустишь, даже когда не в игре.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>`,
    title: 'Кросс-игровые связи',
    desc: 'Играешь в несколько ММО? Твоя репутация и связи сохраняются при переходе между играми.',
  },
];

const guildBenefits = [
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>`,
    title: 'Управление составом',
    desc: 'Ростер, роли, права доступа — полный контроль. Офицеры и лидеры видят всё в одном месте.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>`,
    title: 'Календарь событий',
    desc: 'Рейды, ивенты, PvP-активности — планируйте расписание с записью и напоминаниями.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>`,
    title: 'Заявки и рекрутинг',
    desc: 'Настраиваемые анкеты, автоматический приём по классам, комментарии офицеров к каждой заявке.',
  },
  {
    icon: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`,
    title: 'Блог и новости',
    desc: 'Внутренние посты, гайды, отчёты о рейдах — ведите летопись гильдии и привлекайте новых игроков.',
  },
];

const stats = [
  { value: 3, label: 'Поддерживаемых игры' },
  { value: 0, label: 'Персонажей на аккаунт', display: '∞' },
  { value: 24, label: 'Доступ к платформе', display: '24/7' },
];

const features = [
  { title: 'Персонажи', desc: 'Создавай персонажей с привязкой к игре, серверу и классу. Теги, изображения, фильтры.' },
  { title: 'Заявки', desc: 'Кастомные анкеты для вступления. Настраиваемые поля, комментарии офицеров.' },
  { title: 'Рейды', desc: 'Формируй составы рейдов из участников гильдии с распределением по ролям.' },
  { title: 'Календарь', desc: 'Разовые, ежедневные, еженедельные, ежемесячные события с записью.' },
  { title: 'Блог', desc: 'Гильдейские, игровые и общие посты. Единая лента с фильтрацией.' },
  { title: 'Голосования', desc: 'Опросы внутри гильдии для принятия коллективных решений.' },
];

const steps = [
  { num: '01', title: 'Создай профиль', desc: 'Зарегистрируйся и добавь своих персонажей — игру, сервер, класс.' },
  { num: '02', title: 'Найди гильдию', desc: 'Используй фильтры или создай свою гильдию и начни набор.' },
  { num: '03', title: 'Играй вместе', desc: 'Рейды, ивенты, блог — управляй сообществом прямо на платформе.' },
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

// --- Animated counters ---
const animatedStats = ref(stats.map(() => 0));
const statsRevealed = ref(false);

function animateCounters() {
  if (statsRevealed.value) return;
  statsRevealed.value = true;
  stats.forEach((s, i) => {
    if (s.display) {
      animatedStats.value[i] = s.value;
      return;
    }
    const target = s.value;
    const duration = 1200;
    const start = performance.now();
    function tick(now: number) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      animatedStats.value[i] = Math.round(eased * target);
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
}

// --- Parallax for hero decorations ---
const mouseX = ref(0);
const mouseY = ref(0);

function handleMouseMove(e: MouseEvent) {
  mouseX.value = (e.clientX / window.innerWidth - 0.5) * 20;
  mouseY.value = (e.clientY / window.innerHeight - 0.5) * 20;
}

onMounted(() => window.addEventListener('mousemove', handleMouseMove, { passive: true }));
onUnmounted(() => window.removeEventListener('mousemove', handleMouseMove));
</script>

<template>
  <main id="main-content" class="overflow-x-hidden" aria-labelledby="landing-hero-heading">
    <!-- Hero -->
    <section
      class="relative overflow-hidden border-b border-border bg-gradient-to-b from-background via-background to-muted/30"
      aria-label="Главный экран"
    >
      <!-- Animated gradient orbs -->
      <div
        class="pointer-events-none absolute -top-32 -left-32 h-[500px] w-[500px] rounded-full bg-primary/5 blur-[100px] transition-transform duration-[2000ms] ease-out"
        :style="{ transform: `translate(${mouseX * 0.8}px, ${mouseY * 0.8}px)` }"
      />
      <div
        class="pointer-events-none absolute -bottom-40 -right-40 h-[400px] w-[400px] rounded-full bg-primary/5 blur-[100px] transition-transform duration-[2000ms] ease-out"
        :style="{ transform: `translate(${mouseX * -0.6}px, ${mouseY * -0.6}px)` }"
      />

      <!-- Floating particles -->
      <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div
          v-for="n in 6"
          :key="n"
          class="absolute h-1 w-1 rounded-full bg-primary/20"
          :class="n % 2 === 0 ? 'animate-float-slow' : 'animate-float'"
          :style="{
            left: `${10 + n * 15}%`,
            top: `${15 + (n * 23) % 60}%`,
            animationDelay: `${n * 0.7}s`,
            width: `${3 + (n % 3) * 2}px`,
            height: `${3 + (n % 3) * 2}px`,
          }"
        />
      </div>

      <div class="container relative py-20 md:py-32">
        <div class="mx-auto flex max-w-4xl flex-col items-center gap-6 text-center">

          <h1
            id="landing-hero-heading"
            class="animate-in fade-in slide-in-from-bottom-3 duration-700 delay-100 fill-mode-backwards text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl lg:text-7xl"
          >
            Твоя гильдия.<br />
            <span class="hero-gradient-text">Твоя команда.</span>
          </h1>

          <p class="max-w-2xl text-lg text-muted-foreground md:text-xl animate-in fade-in slide-in-from-bottom-4 duration-1000 delay-200 fill-mode-backwards">
            gg-hub — платформа для игроков MMORPG: находи гильдии в Throne and Liberty, Aion 2 и Black Desert,
            собирай рейды, веди календарь событий и управляй сообществом — всё в одном месте.
          </p>

          <div class="flex flex-wrap justify-center gap-4 animate-in fade-in slide-in-from-bottom-4 duration-1000 delay-300 fill-mode-backwards">
            <RouterLink to="/register">
              <Button size="lg" class="hero-btn rounded-lg text-base px-8">
                Начать бесплатно
              </Button>
            </RouterLink>
            <RouterLink to="/guilds">
              <Button variant="outline" size="lg" class="rounded-lg text-base px-8 transition-all duration-300 hover:scale-105">
                Найти гильдию
              </Button>
            </RouterLink>
          </div>

          <!-- Scroll indicator -->
          <div class="mt-8 animate-bounce opacity-40">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
          </div>
        </div>
      </div>
    </section>

    <!-- Games ticker -->
    <section class="border-b border-border bg-muted/30 overflow-hidden" aria-label="Поддерживаемые игры">
      <div class="container py-10">
        <p
          :ref="setRef('games')"
          data-reveal-id="games"
          class="mb-6 text-center text-sm font-medium uppercase tracking-wider text-muted-foreground transition-all duration-500"
          :class="show('games') ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
        >
          Поддерживаемые игры
        </p>
        <div class="flex flex-wrap items-center justify-center gap-6 md:gap-12">
          <div
            v-for="(game, i) in games"
            :key="game.slug"
            class="text-lg font-semibold text-muted-foreground/70 transition-all hover:text-foreground hover:scale-110 md:text-xl"
            :class="show('games') ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
            :style="{ transitionDelay: `${200 + i * 150}ms`, transitionDuration: '600ms' }"
          >
            {{ game.name }}
          </div>
        </div>
      </div>
    </section>

    <!-- Player Benefits -->
    <section class="container py-16 md:py-24" aria-labelledby="section-players-heading">
      <div
        :ref="setRef('players-header')"
        data-reveal-id="players-header"
        class="mx-auto max-w-3xl text-center transition-all duration-700"
        :class="show('players-header') ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
      >
        <h2 id="section-players-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Забудь о хаосе в поиске гильдии
        </h2>
        <p class="mt-4 text-lg text-muted-foreground">
          Больше не нужно листать форумы и Discord-каналы. Всё, что нужно игроку — здесь.
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
          class="group cursor-default transition-all duration-500 hover:shadow-lg hover:-translate-y-1"
          :class="show('players-cards') ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'"
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

    <Separator />

    <!-- Guild Benefits -->
    <section class="container py-16 md:py-24" aria-labelledby="section-guilds-heading">
      <div
        :ref="setRef('guild-header')"
        data-reveal-id="guild-header"
        class="mx-auto max-w-3xl text-center transition-all duration-700"
        :class="show('guild-header') ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
      >2048
        <h2 id="section-guilds-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Управляй гильдией как профессионал
        </h2>
        <p class="mt-4 text-lg text-muted-foreground">
          Инструменты, которых не хватает в самой игре и в Discord. Всё для лидеров и офицеров.
        </p>
      </div>

      <div
        :ref="setRef('guild-cards')"
        data-reveal-id="guild-cards"
        class="mx-auto mt-12 grid max-w-5xl gap-6 sm:grid-cols-2"
      >
        <Card
          v-for="(b, i) in guildBenefits"
          :key="b.title"
          class="group cursor-default transition-all duration-500 hover:shadow-lg hover:-translate-y-1"
          :class="[
            show('guild-cards') ? 'opacity-100' : 'opacity-0',
            show('guild-cards') ? 'translate-x-0' : (i % 2 === 0 ? '-translate-x-10' : 'translate-x-10'),
          ]"
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

    <Separator />

    <!-- How it works -->
    <section class="container py-16 md:py-24" aria-labelledby="section-steps-heading">
      <div
        :ref="setRef('steps-header')"
        data-reveal-id="steps-header"
        class="mx-auto max-w-3xl text-center transition-all duration-700"
        :class="show('steps-header') ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
      >
        <Badge variant="secondary" class="mb-4">Как это работает</Badge>
        <h2 id="section-steps-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Три шага до своей команды
        </h2>
      </div>

      <div
        :ref="setRef('steps')"
        data-reveal-id="steps"
        class="mx-auto mt-12 grid max-w-4xl gap-8 md:grid-cols-3"
      >
        <div
          v-for="(step, i) in steps"
          :key="step.num"
          class="group text-center transition-all duration-600"
          :class="show('steps') ? 'opacity-100 translate-y-0 scale-100' : 'opacity-0 translate-y-8 scale-95'"
          :style="{ transitionDelay: `${i * 200}ms` }"
        >
          <div class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary text-primary-foreground text-xl font-bold transition-all duration-500 group-hover:scale-110 group-hover:shadow-lg group-hover:shadow-primary/25">
            {{ step.num }}
            <div class="absolute inset-0 rounded-full bg-primary/20 animate-ping opacity-0 group-hover:opacity-75" style="animation-duration: 1.5s" />
          </div>
          <!-- Connector line (hidden on mobile) -->
          <div
            v-if="i < steps.length - 1"
            class="absolute top-8 left-[calc(50%+2rem)] hidden h-px w-[calc(100%-4rem)] bg-border md:block"
            :class="show('steps') ? 'scale-x-100' : 'scale-x-0'"
            :style="{ transitionDelay: `${400 + i * 200}ms`, transitionDuration: '800ms', transformOrigin: 'left' }"
          />
          <h3 class="mt-5 text-lg font-semibold transition-colors duration-300 group-hover:text-primary">{{ step.title }}</h3>
          <p class="mt-2 text-sm text-muted-foreground leading-relaxed">{{ step.desc }}</p>
        </div>
      </div>
    </section>

    <Separator />

    <!-- Features grid -->
    <section class="container py-16 md:py-24" aria-labelledby="section-features-heading">
      <div
        :ref="setRef('features-header')"
        data-reveal-id="features-header"
        class="mx-auto max-w-3xl text-center transition-all duration-700"
        :class="show('features-header') ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
      >
        <h2 id="section-features-heading" class="text-3xl font-bold tracking-tight sm:text-4xl">
          Всё для жизни гильдии
        </h2>
      </div>

      <div
        :ref="setRef('features')"
        data-reveal-id="features"
        class="mx-auto mt-12 grid max-w-5xl gap-4 sm:grid-cols-2 lg:grid-cols-3"
      >
        <Card
          v-for="(f, i) in features"
          :key="f.title"
          class="group cursor-default overflow-hidden transition-all duration-500 hover:shadow-lg hover:-translate-y-1"
          :class="show('features') ? 'opacity-100 scale-100' : 'opacity-0 scale-90'"
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

    <!-- CTA -->
    <section class="relative border-t border-border overflow-hidden" aria-label="Регистрация">
      <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-muted/30 to-primary/5" />
      <div
        :ref="setRef('cta')"
        data-reveal-id="cta"
        class="container relative py-16 md:py-24"
      >
        <div
          class="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center transition-all duration-700"
          :class="show('cta') ? 'opacity-100 translate-y-0 scale-100' : 'opacity-0 translate-y-8 scale-95'"
        >
          <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
            Готов найти свою команду?
          </h2>
          <p class="max-w-xl text-lg text-muted-foreground">
            Присоединяйся к gg-hub — бесплатной платформе для игроков и гильдий MMORPG.
            Throne and Liberty, Aion 2, Black Desert и другие игры.
          </p>
          <div class="flex flex-wrap justify-center gap-4">
            <RouterLink to="/register">
              <Button size="lg" class="hero-btn rounded-lg text-base px-8">
                Создать аккаунт
              </Button>
            </RouterLink>
            <RouterLink to="/guilds">
              <Button variant="outline" size="lg" class="rounded-lg text-base px-8 transition-all duration-300 hover:scale-105">
                Смотреть гильдии
              </Button>
            </RouterLink>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.hero-gradient-text {
  background: linear-gradient(135deg, hsl(var(--primary)) 0%, hsl(270 70% 60%) 50%, hsl(var(--primary)) 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gradient-shift 4s ease-in-out infinite;
}

:global(.dark) .hero-gradient-text {
  background: linear-gradient(135deg, oklch(0.922 0 0) 0%, oklch(0.75 0.15 280) 50%, oklch(0.922 0 0) 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: gradient-shift 4s ease-in-out infinite;
}

@keyframes gradient-shift {
  0%, 100% { background-position: 0% center; }
  50% { background-position: 100% center; }
}

.hero-btn {
  position: relative;
  overflow: hidden;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hero-btn:hover {
  transform: scale(1.05);
  box-shadow: 0 8px 30px -8px hsl(var(--primary) / 0.4);
}
.hero-btn::after {
  content: '';
  position: absolute;
  inset: 0;
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
</style>
