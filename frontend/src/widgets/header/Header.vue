<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import {
  Avatar,
  Button,
  Sheet,
  Separator,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  SiteLogo,
} from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { useThemeStore } from '@/stores/theme';
import type { ThemePreference } from '@/stores/theme';
import { notificationsApi, type NotificationItem } from '@/shared/api/notificationsApi';
import { guildsApi, type UserPollItem } from '@/shared/api/guildsApi';
import NotificationsDrawer from '@/widgets/header/NotificationsDrawer.vue';
import PollsDrawer from '@/widgets/header/PollsDrawer.vue';

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
const expandedId = ref<number | null>(null);
const deletingNotificationId = ref<number | null>(null);

const pollsDrawerOpen = ref(false);
const polls = ref<UserPollItem[]>([]);
const loadingPolls = ref(false);

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

async function loadPolls() {
  if (!auth.isAuthenticated) return;
  loadingPolls.value = true;
  polls.value = [];
  try {
    const gameId = siteContext.game?.id ?? null;
    polls.value = await guildsApi.getUserPolls(gameId);
  } catch {
    polls.value = [];
  } finally {
    loadingPolls.value = false;
  }
}

function onPollUpdated(updated: UserPollItem) {
  const idx = polls.value.findIndex((p) => p.guild_id === updated.guild_id && p.id === updated.id);
  if (idx !== -1) polls.value[idx] = updated;
}

const unvotedPollsCount = computed(() =>
  polls.value.filter((p) => !p.is_closed && p.my_vote_option_id == null).length
);

watch(pollsDrawerOpen, (open) => {
  if (open && auth.isAuthenticated) loadPolls();
});

watch(() => [siteContext.game?.id, auth.isAuthenticated], () => {
  if (auth.isAuthenticated) loadPolls();
}, { immediate: false });

watch(() => siteContext.pollsRefreshTrigger, (val) => {
  if (val > 0 && auth.isAuthenticated) loadPolls();
});

watch(notificationsDrawerOpen, (open) => {
  if (open && auth.isAuthenticated) loadNotifications();
});

watch(() => auth.isAuthenticated, (isAuth) => {
  if (isAuth) {
    loadNotifications();
    loadPolls();
  } else {
    notifications.value = [];
    unreadCount.value = 0;
    polls.value = [];
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

async function deleteNotification(id: number) {
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
  { to: '/guilds', label: 'Гильдии' },
  { to: '/games', label: 'Игры' },
];

/** Активный пункт верхнего меню: главная только по точному пути, остальные — раздел и вложенные URL. */
function isNavActive(itemTo: string): boolean {
  const path = route.path;
  if (itemTo === '/') {
    return path === '/';
  }
  return path === itemTo || path.startsWith(`${itemTo}/`);
}
</script>

<template>
  <header class="sticky top-0 z-20 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
    <div
      class="flex h-14 items-center gap-2 px-4 md:grid md:grid-cols-[1fr_auto_1fr] md:items-center md:gap-4 md:px-6"
    >
      <div class="flex min-w-0 shrink-0 items-center gap-2">
        <RouterLink to="/" class="group flex shrink-0 items-center gap-2 font-semibold">
          <SiteLogo :size="36" class="transition-transform group-hover:scale-105" />
        </RouterLink>
        <div class="shrink-0 md:hidden">
          <slot name="sidebar-trigger" />
        </div>
      </div>

      <nav
        class="hidden items-center justify-center gap-1 md:flex"
        aria-label="Основная навигация"
      >
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="relative rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-[color,opacity] hover:text-foreground/90"
          :class="
            isNavActive(item.to)
              ? 'text-foreground after:pointer-events-none after:absolute after:inset-x-3 after:bottom-1 after:h-px after:rounded-full after:bg-primary/45'
              : ''
          "
          :aria-current="isNavActive(item.to) ? 'page' : undefined"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="flex min-w-0 flex-1 items-center justify-end gap-2 md:flex-none">
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
        <!-- Голосования (только для авторизованных) -->
        <template v-if="auth.isAuthenticated">
          <PollsDrawer
            v-model:open="pollsDrawerOpen"
            :polls="polls"
            :loading="loadingPolls"
            :unvoted-count="unvotedPollsCount"
            @poll-updated="onPollUpdated"
          />
        </template>
        <!-- Оповещения (только для авторизованных) -->
        <template v-if="auth.isAuthenticated">
          <NotificationsDrawer
            v-model:open="notificationsDrawerOpen"
            :notifications="notifications"
            :unread-count="unreadCount"
            :loading="loadingNotifications"
            :loading-more="loadingMoreNotifications"
            :has-more="notificationsHasMore"
            :expanded-id="expandedId"
            :deleting-id="deletingNotificationId"
            :timezone="auth.user?.timezone"
            @load="loadNotifications"
            @load-more="loadMoreNotifications"
            @notification-click="onNotificationClick"
            @notification-mouse-enter="onNotificationMouseEnter"
            @delete="deleteNotification"
          />
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
                <RouterLink to="/my-characters" class="cursor-pointer">
                  Мои персонажи
                </RouterLink>
              </DropdownMenuItem>
              <DropdownMenuItem as-child>
                <RouterLink to="/my-posts" class="cursor-pointer">
                  Мои посты
                </RouterLink>
              </DropdownMenuItem>
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

<!--          <RouterLink to="/login">-->
<!--            <Button variant="ghost" size="sm" class="hidden sm:inline-flex">Войти</Button>-->
<!--          </RouterLink>-->

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
              class="rounded-lg border-l-2 border-transparent py-2 pl-[calc(0.75rem-2px)] pr-3 text-base font-medium transition-colors hover:bg-muted/50"
              :class="
                isNavActive(item.to)
                  ? 'border-primary/35 text-foreground'
                  : 'text-muted-foreground'
              "
              :aria-current="isNavActive(item.to) ? 'page' : undefined"
              @click="mobileMenuOpen = false"
            >
              {{ item.label }}
            </RouterLink>
            <Separator />
            <template v-if="auth.isAuthenticated">
              <RouterLink to="/my-posts" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent" @click="mobileMenuOpen = false">
                Мои посты
              </RouterLink>
              <RouterLink to="/profile" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent" @click="mobileMenuOpen = false">
                Профиль
              </RouterLink>

              <button type="button" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent text-left" @click="auth.logout(); mobileMenuOpen = false">
                Выйти
              </button>

            </template>
            <template v-else>
<!--              <RouterLink to="/login" class="rounded-lg px-3 py-2 text-base font-medium hover:bg-accent" @click="mobileMenuOpen = false">Войти</RouterLink>-->
            </template>
          </div>
        </Sheet>
      </div>
    </div>
  </header>
</template>
