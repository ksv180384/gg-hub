<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import {
  Avatar,
  Badge,
  Button,
  RelativeTime,
  Sheet,
  Separator,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { useThemeStore } from '@/stores/theme';
import type { ThemePreference } from '@/stores/theme';
import { notificationsApi, type NotificationItem } from '@/shared/api/notificationsApi';

const route = useRoute();
const auth = useAuthStore();
const siteContext = useSiteContextStore();
const theme = useThemeStore();
const mobileMenuOpen = ref(false);
const notificationsDrawerOpen = ref(false);
const notifications = ref<NotificationItem[]>([]);
const unreadCount = ref(0);
const loadingNotifications = ref(false);
const loadingMoreNotifications = ref(false);
const notificationsHasMore = ref(false);
const notificationsPage = ref(1);
const notificationsListRef = ref<HTMLElement | null>(null);
const expandedId = ref<number | null>(null);
const deletingNotificationId = ref<number | null>(null);

const badgeText = computed(() => {
  if (unreadCount.value <= 0) return '';
  if (unreadCount.value > 9) return '9+';
  return String(unreadCount.value);
});

function truncateMessage(msg: string, max = 20) {
  if (msg.length <= max) return msg;
  return msg.slice(0, max) + '…';
}

async function loadNotifications() {
  if (!auth.isAuthenticated) return;
  loadingNotifications.value = true;
  notifications.value = [];
  notificationsPage.value = 1;
  try {
    const { data, unreadCount: count, hasMore, currentPage } = await notificationsApi.getList(1);
    notifications.value = data;
    unreadCount.value = count;
    notificationsHasMore.value = hasMore;
    notificationsPage.value = currentPage;
  } catch {
    notifications.value = [];
  } finally {
    loadingNotifications.value = false;
  }
}

async function loadMoreNotifications() {
  if (!notificationsHasMore.value || loadingMoreNotifications.value || loadingNotifications.value) return;
  loadingMoreNotifications.value = true;
  try {
    const nextPage = notificationsPage.value + 1;
    const { data, unreadCount: count, hasMore, currentPage } = await notificationsApi.getList(nextPage);
    notifications.value = [...notifications.value, ...data];
    unreadCount.value = count;
    notificationsHasMore.value = hasMore;
    notificationsPage.value = currentPage;
  } finally {
    loadingMoreNotifications.value = false;
  }
}

function onNotificationsScroll(e: Event) {
  const el = e.target as HTMLElement;
  if (!el || !notificationsHasMore.value || loadingMoreNotifications.value) return;
  const threshold = 80;
  if (el.scrollHeight - el.scrollTop - el.clientHeight < threshold) {
    loadMoreNotifications();
  }
}

watch(notificationsDrawerOpen, (open) => {
  if (open && auth.isAuthenticated) loadNotifications();
});

watch(() => auth.isAuthenticated, (isAuth) => {
  if (isAuth) loadNotifications();
  else {
    notifications.value = [];
    unreadCount.value = 0;
  }
}, { immediate: true });

function onNotificationClick(n: NotificationItem) {
  expandedId.value = expandedId.value === n.id ? null : n.id;
}

async function onNotificationMouseEnter(n: NotificationItem) {
  if (n.read_at) return;
  try {
    await notificationsApi.markAsRead(n.id);
    unreadCount.value = Math.max(0, unreadCount.value - 1);
    const idx = notifications.value.findIndex((x) => x.id === n.id);
    if (idx !== -1) notifications.value[idx] = { ...n, read_at: new Date().toISOString() };
  } catch {
    // ignore
  }
}

async function deleteNotification(e: Event, id: number) {
  e.stopPropagation();
  if (deletingNotificationId.value !== null) return;
  deletingNotificationId.value = id;
  try {
    await notificationsApi.delete(id);
    notifications.value = notifications.value.filter((x) => x.id !== id);
    unreadCount.value = notifications.value.filter((x) => !x.read_at).length;
  } catch {
    // ignore
  } finally {
    deletingNotificationId.value = null;
  }
}

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
    <div class="flex h-14 items-center gap-2 md:gap-4 px-4 md:px-6">
      <RouterLink to="/" class="flex items-center gap-2 font-semibold md:mr-6 shrink-0">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground text-lg">⚔</span>
        <span class="hidden font-bold sm:inline-block">{{ siteTitle }}</span>
      </RouterLink>
      <div class="md:hidden shrink-0">
        <slot name="sidebar-trigger" />
      </div>

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
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Тема оформления">
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
        <!-- Оповещения (только для авторизованных) -->
        <template v-if="auth.isAuthenticated">
          <Sheet v-model:open="notificationsDrawerOpen" side="right" class="w-full max-w-sm">
            <template #trigger>
              <button
                type="button"
                variant="ghost"
                class="relative flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                aria-label="Оповещения"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[1.125rem] w-[1.125rem]">
                  <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                  <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
                <Badge v-if="badgeText" variant="destructive" class="absolute -right-1 -top-1 text-[10px] max-w-[10px] flex items-center justify-center bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300  hover:text-red-200">
                  {{ badgeText }}
                </Badge>
              </button>
            </template>
            <template #title>Оповещения</template>
            <div class="flex min-h-0 flex-1 flex-col">
              <div
                ref="notificationsListRef"
                class="min-h-0 flex-1 flex-col gap-1 overflow-y-auto pt-2 flex"
                @scroll="onNotificationsScroll"
              >
                <p v-if="loadingNotifications" class="px-2 py-4 text-sm text-muted-foreground">Загрузка…</p>
                <template v-else-if="notifications.length === 0">
                  <p class="px-2 py-4 text-sm text-muted-foreground">Нет оповещений</p>
                </template>
                <template v-else>
                  <div
                    v-for="n in notifications"
                    :key="n.id"
                    role="button"
                    tabindex="0"
                    class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent cursor-pointer"
                    :class="[
                    { 'bg-muted/50': expandedId === n.id },
                    !n.read_at && 'bg-primary/10'
                  ]"
                    @click="onNotificationClick(n)"
                    @keydown.enter.prevent="onNotificationClick(n)"
                    @keydown.space.prevent="onNotificationClick(n)"
                    @mouseenter="onNotificationMouseEnter(n)"
                  >
                    <div class="min-w-0 flex-1">
                    <span class="block break-words">
                      {{ expandedId === n.id ? n.message : truncateMessage(n.message, 60) }}
                    </span>
                      <span v-if="n.created_at" class="mt-1.5 block text-xs text-muted-foreground">
                      <RelativeTime :date="n.created_at" :timezone="auth.user?.timezone" tag="time" class="text-xs text-muted-foreground" />
                    </span>
                    </div>
                    <button
                      type="button"
                      class="shrink-0 rounded p-1 opacity-70 hover:opacity-100 hover:bg-destructive/20 disabled:pointer-events-none"
                      aria-label="Удалить"
                      :disabled="deletingNotificationId === n.id"
                      @click.stop="deleteNotification($event, n.id)"
                    >
                      <svg
                        v-if="deletingNotificationId === n.id"
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        class="h-3.5 w-3.5 animate-spin"
                      >
                        <path d="M21 12a9 9 0 1 1-6.22-8.56" />
                      </svg>
                      <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="h-3.5 w-3.5"
                      >
                        <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                        <line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                      </svg>
                    </button>
                  </div>
                  <div v-if="loadingMoreNotifications" class="flex items-center justify-center gap-2 px-2 py-3">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4 shrink-0 animate-spin text-muted-foreground">
                    <path d="M21 12a9 9 0 1 1-6.22-8.56" />
                  </svg>
                  <span class="text-sm text-muted-foreground">Загрузка…</span>
                </div>
                </template>
              </div>
            </div>
          </Sheet>
        </template>
        <!-- Тема: dropdown в стиле shadcn -->
        <!-- Авторизован: по клику на аватар — дропдаун с именем, профиль, выход -->
        <template v-if="auth.isAuthenticated">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button
                type="button"
                class="rounded-full outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                aria-label="Меню пользователя"
              >
                <Avatar
                  :src="auth.user?.avatar_url ?? undefined"
                  :fallback="(auth.user?.name?.slice(0, 2) || '??').toUpperCase()"
                  class="h-8 w-8 cursor-pointer"
                />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="w-56" align="end">
              <DropdownMenuLabel class="font-normal">
                <p class="text-sm font-medium">{{ auth.user?.name }}</p>
                <p class="text-xs text-muted-foreground truncate">{{ auth.user?.email }}</p>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem as-child>
                <RouterLink to="/profile" class="cursor-pointer">
                  Профиль
                </RouterLink>
              </DropdownMenuItem>
              <DropdownMenuItem class="cursor-pointer text-destructive focus:text-destructive" @select="auth.logout()">
                Выйти
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
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
              <RouterLink to="/profile" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent" @click="mobileMenuOpen = false">
                Профиль
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
