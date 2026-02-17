<script setup lang="ts">
import { computed } from 'vue';
import { RouterView } from 'vue-router';
import { Header } from '@/widgets/header';
import { GameSidebar } from '@/widgets/game-sidebar';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';

const auth = useAuthStore();
const siteContext = useSiteContextStore();

// Боковое меню доступно на игровом субдомене (Персонажи, Гильдия) и на админ-субдомене (Управление).
const showSidebar = computed(
  () =>
    auth.isAuthenticated &&
    (siteContext.isGameSubdomain || siteContext.isAdmin)
);
</script>

<template>
  <div class="min-h-svh flex flex-col bg-background">
    <Header />
    <div class="flex flex-1">
      <GameSidebar v-if="showSidebar" />
      <main class="flex-1 min-w-0">
        <RouterView />
      </main>
    </div>
    <footer class="border-t py-6 md:py-0">
      <div class="container flex flex-col items-center justify-between gap-4 md:h-14 md:flex-row">
        <p class="text-center text-sm text-muted-foreground md:text-left">
          GG Hub — соцсеть для игроков MMORPG
        </p>
      </div>
    </footer>
  </div>
</template>
