<script setup lang="ts">
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { Spinner } from '@/shared/ui';
import { useRouteLoadingStore } from '@/stores/routeLoading';
import { Header } from '@/widgets/header';
import { GameSidebar, GameSidebarContent } from '@/widgets/game-sidebar';
import GgHubJournalBanner from '@/widgets/journal-promo/GgHubJournalBanner.vue';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';

const auth = useAuthStore();
const siteContext = useSiteContextStore();
const routeLoading = useRouteLoadingStore();
const route = useRoute();

// Боковое меню доступно на игровом субдомене (Персонажи, Гильдия) и на админ-субдомене (Управление),
// но показывается только для авторизованных пользователей (включая мобильную версию).
const sidebarAvailable = computed(() => siteContext.isGameSubdomain || siteContext.isAdmin);
const showSidebar = computed(() => auth.isAuthenticated && sidebarAvailable.value);

const useContentShell = computed(
  () =>
    route.meta.contentShell === true
    || (route.name === 'home' && siteContext.isGameSubdomain),
);

const showJournalBanner = computed(() => route.meta.journalBanner === true);
</script>

<template>
  <div class="min-h-svh flex flex-col bg-background pb-[calc(4rem+env(safe-area-inset-bottom))] md:pb-0">
    <Header :mobile-menu-sidebar-visible="showSidebar">
      <template #mobile-menu-sidebar="{ closeMenu }">
        <GameSidebarContent embedded suppress-embedded-heading @navigate="closeMenu" />
      </template>
    </Header>
    <div class="flex min-h-0 flex-1 items-stretch">
      <!--
        Колонка под сайдбар на desktop, если субдомен игры/админки — всегда (даже для гостей),
        чтобы дерево SSR и гидратации совпадало и не было CLS при догрузке пользователя.
      -->
      <div
        v-if="sidebarAvailable"
        class="hidden min-h-0 w-56 shrink-0 md:flex md:flex-col"
      >
        <GameSidebar v-if="showSidebar" class="min-h-0 flex-1" />
      </div>
      <main class="relative min-h-0 flex-1 min-w-0">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div
            v-show="routeLoading.isLoading"
            :class="[
              'fixed top-0 left-0 right-0 bottom-0 z-10 flex flex-col items-center justify-center gap-4 bg-background/95 backdrop-blur-sm md:top-14',
              showSidebar && 'md:left-56',
            ]"
            aria-live="polite"
            :aria-busy="routeLoading.isLoading"
          >
            <Spinner />
            <p class="text-sm text-muted-foreground">Загрузка…</p>
          </div>
        </Transition>
        <div :class="useContentShell ? 'container py-6 md:py-8' : undefined">
          <div
            :class="
              useContentShell
                ? 'flex flex-col gap-8 lg:grid lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-4'
                : undefined
            "
          >
            <div :class="useContentShell ? 'min-w-0' : undefined">
              <RouterView />
              <GgHubJournalBanner
                v-if="showJournalBanner && route.name !== 'home'"
                variant="mobile"
                class="mt-8"
              />
            </div>
            <GgHubJournalBanner
              v-if="useContentShell && showJournalBanner"
              variant="desktop"
            />
          </div>
        </div>
      </main>
    </div>
    <footer class="landing-home-footer relative overflow-hidden border-t border-border/60" aria-label="Подвал страницы">
      <div
        class="container relative z-[1] grid grid-cols-1 items-center gap-8 py-10 md:grid-cols-[1fr_auto_1fr] md:gap-6 md:py-12"
      >
        <div
          class="text-center text-sm leading-relaxed text-foreground md:justify-self-start md:text-left"
        >
          <p class="font-medium">Платформа для развития игровых сообществ</p>
          <p class="mt-1 text-muted-foreground">© 2026 GG-HUB</p>
          <p class="mt-1 text-muted-foreground">
            Email:
            <a
              class="text-foreground underline-offset-4 hover:underline"
              href="mailto:support@gg-hub.ru"
            >
              support@gg-hub.ru
            </a>
          </p>
        </div>
        <div class="hidden md:block" aria-hidden="true" />
      </div>
    </footer>
  </div>
</template>

<style>
/* Глобально: html.dark + класс футера без data-v, чтобы фон точно перекрывал тему. */
.landing-home-footer {
  background-color: #080a0d;
  background-image: url('/assets/images/footer.webp');
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
  color: rgba(232, 225, 214, 0.82);
}

.landing-home-footer::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    linear-gradient(90deg, rgba(5, 7, 10, 0.82) 0%, rgba(5, 7, 10, 0.58) 50%, rgba(5, 7, 10, 0.82) 100%),
    linear-gradient(180deg, rgba(5, 7, 10, 0.72) 0%, rgba(5, 7, 10, 0.52) 45%, rgba(5, 7, 10, 0.78) 100%);
  pointer-events: none;
}

.landing-home-footer p,
.landing-home-footer a {
  color: rgba(232, 225, 214, 0.82) !important;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.58);
}

.landing-home-footer .text-muted-foreground {
  color: rgba(220, 214, 205, 0.62) !important;
}

.landing-home-footer a {
  font-weight: 600;
  text-decoration-color: rgba(214, 181, 104, 0.5);
}

.landing-home-footer a:hover {
  color: rgba(232, 200, 126, 0.9) !important;
}
</style>
