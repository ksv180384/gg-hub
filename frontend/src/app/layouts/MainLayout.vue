<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { Sheet, Button } from '@/shared/ui';
import { Header } from '@/widgets/header';
import { GameSidebar, GameSidebarContent } from '@/widgets/game-sidebar';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';

const auth = useAuthStore();
const siteContext = useSiteContextStore();
const route = useRoute();
const sidebarOpen = ref(false);

// Боковое меню доступно на игровом субдомене (Персонажи, Гильдия) и на админ-субдомене (Управление).
const showSidebar = computed(
  () =>
    auth.isAuthenticated &&
    (siteContext.isGameSubdomain || siteContext.isAdmin)
);

watch(() => route.path, () => {
  sidebarOpen.value = false;
});
</script>

<template>
  <div class="min-h-svh flex flex-col bg-background">
    <Header>
      <template #sidebar-trigger>
        <Button
          v-if="showSidebar"
          variant="ghost"
          size="icon"
          class="md:hidden h-9 w-9 min-w-9 min-h-9"
          aria-label="Открыть меню"
          @click="sidebarOpen = true"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>
          </svg>
        </Button>
      </template>
    </Header>
    <div class="flex flex-1">
      <GameSidebar v-if="showSidebar" />
      <main class="flex-1 min-w-0">
        <RouterView />
      </main>
    </div>
    <!-- Мобильный сайдбар (drawer слева) -->
    <Sheet v-if="showSidebar" v-model:open="sidebarOpen" side="left" class="w-64 p-0 sm:max-w-[14rem] h-full">
      <GameSidebarContent />
    </Sheet>
    <footer class="border-t py-6 md:py-0">
      <div class="container flex flex-col items-center justify-between gap-4 md:h-14 md:flex-row">
        <p class="text-center text-sm text-muted-foreground md:text-left">
          GG Hub — соцсеть для игроков MMORPG
        </p>
      </div>
    </footer>
  </div>
</template>
