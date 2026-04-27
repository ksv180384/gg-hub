<script setup lang="ts">
import { computed } from 'vue';
import { RouterView } from 'vue-router';
import { Spinner, SiteLogo } from '@/shared/ui';
import { useRouteLoadingStore } from '@/stores/routeLoading';
import { Header } from '@/widgets/header';
import { GameSidebar, GameSidebarContent } from '@/widgets/game-sidebar';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';

const auth = useAuthStore();
const siteContext = useSiteContextStore();
const routeLoading = useRouteLoadingStore();

// Боковое меню доступно на игровом субдомене (Персонажи, Гильдия) и на админ-субдомене (Управление).
// На публичных страницах пользователь может догружаться асинхронно, поэтому не привязываем
// саму “обвязку” сайдбара к isAuthenticated — контент внутри уже сам решает, что показывать.
const showSidebar = computed(() => siteContext.isGameSubdomain || siteContext.isAdmin);
</script>

<template>
  <div class="min-h-svh flex flex-col bg-background">
    <Header>
      <template v-if="showSidebar" #mobile-menu-sidebar="{ closeMenu }">
        <GameSidebarContent embedded suppress-embedded-heading @navigate="closeMenu" />
      </template>
    </Header>
    <div class="flex flex-1">
      <GameSidebar v-if="showSidebar" />
      <main class="relative flex-1 min-w-0">
        <Transition
          enter-active-class="ease-out duration-200"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="ease-in duration-150"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div
            v-if="routeLoading.isLoading"
            :class="[
              'fixed top-14 left-0 right-0 bottom-0 z-10 flex flex-col items-center justify-center gap-4 bg-background/95 backdrop-blur-sm',
              showSidebar && 'md:left-56',
            ]"
            aria-live="polite"
            aria-busy="true"
          >
            <Spinner />
            <p class="text-sm text-muted-foreground">Загрузка…</p>
          </div>
        </Transition>
        <RouterView />
      </main>
    </div>
    <footer class="landing-home-footer relative border-t border-border/60" aria-label="Подвал страницы">
      <div
        class="container grid grid-cols-1 items-center gap-8 py-10 md:grid-cols-[1fr_auto_1fr] md:gap-6 md:py-12"
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
/* Глобально: html.dark + класс футера без data-v, чтобы тёмный фон точно перекрывал светлый. */
.landing-home-footer {
  background-color: #ebe9e6;
  background-image:
    radial-gradient(ellipse 140% 100% at 50% -20%, rgba(255, 255, 255, 0.85) 0%, transparent 50%),
    repeating-linear-gradient(
      -12deg,
      transparent,
      transparent 3px,
      rgba(0, 0, 0, 0.018) 3px,
      rgba(0, 0, 0, 0.018) 4px
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.35) 0%, transparent 45%);
}

html.dark .landing-home-footer {
  background-color: var(--background);
  background-image:
    radial-gradient(ellipse 140% 100% at 50% -20%, rgba(255, 255, 255, 0.06) 0%, transparent 50%),
    repeating-linear-gradient(
      -12deg,
      transparent,
      transparent 3px,
      rgba(255, 255, 255, 0.04) 3px,
      rgba(255, 255, 255, 0.04) 4px
    ),
    linear-gradient(180deg, rgba(255, 255, 255, 0.05) 0%, transparent 45%);
}
</style>
