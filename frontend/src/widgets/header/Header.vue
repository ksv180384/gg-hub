<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import { Button, Sheet, Separator } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const auth = useAuthStore();
const mobileMenuOpen = ref(false);

watch(
  () => route.path,
  () => {
    mobileMenuOpen.value = false;
  }
);

const navItems = [
  { to: '/', label: 'Главная' },
  { to: '/news', label: 'Новости' },
  { to: '/guilds', label: 'Гильдии' },
];
</script>

<template>
  <header class="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="flex h-14 items-center gap-4 px-4 md:px-6">
      <RouterLink to="/" class="flex items-center gap-2 font-semibold md:mr-6">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground text-lg">⚔</span>
        <span class="hidden font-bold sm:inline-block">GG Hub</span>
      </RouterLink>

      <nav class="hidden flex-1 items-center gap-6 md:flex">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="text-sm font-medium text-muted-foreground transition-colors hover:text-primary"
          :class="{ 'text-foreground': route.path === item.to }"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="flex flex-1 items-center justify-end gap-2 md:flex-none">
        <!-- Авторизован: имя, смена пароля, выход -->
        <template v-if="auth.isAuthenticated">
          <span class="hidden text-sm text-muted-foreground md:inline">{{ auth.user?.name }}</span>
          <RouterLink to="/change-password">
            <Button variant="ghost" size="sm" class="hidden sm:inline-flex">Сменить пароль</Button>
          </RouterLink>
          <Button variant="ghost" size="sm" class="hidden sm:inline-flex" @click="auth.logout()">Выйти</Button>
        </template>
        <!-- Не авторизован -->
        <template v-else>
          <RouterLink to="/login">
            <Button variant="ghost" size="sm" class="hidden sm:inline-flex">Войти</Button>
          </RouterLink>
          <RouterLink to="/register">
            <Button size="sm" class="hidden sm:inline-flex">Регистрация</Button>
          </RouterLink>
        </template>

        <Sheet v-model:open="mobileMenuOpen" side="right" class="md:hidden">
          <template #trigger>
            <Button variant="ghost" size="icon" aria-label="Открыть меню" class="md:hidden">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>
              </svg>
            </Button>
          </template>
          <div class="flex flex-col gap-4 pt-8">
            <RouterLink
              v-for="item in navItems"
              :key="item.to"
              :to="item.to"
              class="rounded-lg px-3 py-2 text-base font-medium transition-colors hover:bg-accent"
              :class="route.path === item.to ? 'bg-accent text-accent-foreground' : 'text-foreground'"
              @click="mobileMenuOpen = false"
            >
              {{ item.label }}
            </RouterLink>
            <Separator />
            <template v-if="auth.isAuthenticated">
              <RouterLink to="/change-password" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent" @click="mobileMenuOpen = false">
                Сменить пароль
              </RouterLink>
              <button type="button" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent text-left" @click="auth.logout(); mobileMenuOpen = false">
                Выйти
              </button>
            </template>
            <template v-else>
              <RouterLink to="/login" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent" @click="mobileMenuOpen = false">Войти</RouterLink>
              <RouterLink to="/register" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent" @click="mobileMenuOpen = false">Регистрация</RouterLink>
            </template>
          </div>
        </Sheet>
      </div>
    </div>
  </header>
</template>
