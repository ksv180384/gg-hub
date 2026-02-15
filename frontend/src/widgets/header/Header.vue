<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import {
  Button,
  Sheet,
  Separator,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { useThemeStore } from '@/stores/theme';
import type { ThemePreference } from '@/stores/theme';

const route = useRoute();
const auth = useAuthStore();
const siteContext = useSiteContextStore();
const theme = useThemeStore();
const mobileMenuOpen = ref(false);

const siteTitle = computed(() => {
  if (siteContext.isAdmin) return 'Админ · GG Hub';
  if (siteContext.game) return `${siteContext.game.name} · GG Hub`;
  return 'GG Hub';
});

const themeOptions: { value: ThemePreference; label: string }[] = [
  { value: 'light', label: 'Светлая' },
  { value: 'dark', label: 'Тёмная' },
  { value: 'system', label: 'Системная' },
];

function setTheme(value: ThemePreference) {
  theme.setPreference(value);
}

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
  { to: '/games', label: 'Игры' },
];
</script>

<template>
  <header class="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div class="flex h-14 items-center gap-4 px-4 md:px-6">
      <RouterLink to="/" class="flex items-center gap-2 font-semibold md:mr-6">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground text-lg">⚔</span>
        <span class="hidden font-bold sm:inline-block">{{ siteTitle }}</span>
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
        <!-- Тема: dropdown в стиле shadcn -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon" class="h-9 w-9" aria-label="Тема оформления">
              <svg v-if="theme.preference === 'light'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[1.125rem] w-[1.125rem]">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
              </svg>
              <svg v-else-if="theme.preference === 'dark'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[1.125rem] w-[1.125rem]">
                <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[1.125rem] w-[1.125rem]">
                <rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>
              </svg>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent class="w-40" align="end">
            <DropdownMenuGroup>
              <DropdownMenuItem v-for="opt in themeOptions" :key="opt.value" @select="setTheme(opt.value)">
                <span class="mr-2 w-4 text-center">{{ theme.preference === opt.value ? '✓' : '' }}</span>
                {{ opt.label }}
              </DropdownMenuItem>
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>
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
            <div class="flex items-center gap-2 px-3">
              <span class="text-sm font-medium text-muted-foreground">Тема</span>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="outline" size="sm" class="gap-2">
                    <svg v-if="theme.preference === 'light'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                      <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                    <svg v-else-if="theme.preference === 'dark'" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                      <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                      <rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>
                    </svg>
                    {{ themeOptions.find(o => o.value === theme.preference)?.label ?? 'Тема' }}
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-40" align="start">
                  <DropdownMenuGroup>
                    <DropdownMenuLabel>Тема</DropdownMenuLabel>
                    <DropdownMenuItem v-for="opt in themeOptions" :key="opt.value" @select="setTheme(opt.value)">
                      <span class="mr-2 w-4 text-center">{{ theme.preference === opt.value ? '✓' : '' }}</span>
                      {{ opt.label }}
                    </DropdownMenuItem>
                  </DropdownMenuGroup>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <Separator />
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
