<script setup lang="ts">
import { ref, computed, watch, nextTick, useSlots } from 'vue';
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
import { PERMISSION_ACCESS_ADMIN, PERMISSION_VIEW_POLLS } from '@/shared/api/authApi';
import { useNotificationsSocket } from '@/shared/lib/useNotificationsSocket';
import { useGuildPollsSocket } from '@/shared/lib/useGuildPollsSocket';
import NotificationsDrawer from '@/widgets/header/NotificationsDrawer.vue';
import PollsDrawer from '@/widgets/header/PollsDrawer.vue';
import TodaysEventsDrawer from '@/widgets/header/TodaysEventsDrawer.vue';
import { DEFAULT_PRODUCTION_ORIGIN } from '@/seo/homePageSeo';

const route = useRoute();
const auth = useAuthStore();
const siteContext = useSiteContextStore();
const theme = useThemeStore();
const slots = useSlots();
const hasMobileMenuSidebar = computed(() => !!slots['mobile-menu-sidebar']);

const showMobileSidebarAdminBlock = computed(
  () =>
    siteContext.isAdmin &&
    (auth.hasPermission(PERMISSION_ACCESS_ADMIN) || auth.hasPermission(PERMISSION_VIEW_POLLS))
);

/** Заголовок игры/админки в верхней части мобильного меню (над общей навигацией). */
const mobileSheetContextTitle = computed(() => {
  if (!hasMobileMenuSidebar.value) return '';
  if (showMobileSidebarAdminBlock.value) return 'Админ';
  return siteContext.game?.name ?? 'Игра';
});

const mobileMenuOpen = ref(false);

function closeMobileMenu() {
  mobileMenuOpen.value = false;
}
const notificationsDrawerOpen = ref(false);
const notifications = ref<NotificationItem[]>([]);
const unreadCount = ref(0);
const loadingNotifications = ref(false);
const loadingMoreNotifications = ref(false);
const notificationsHasMore = ref(false);
const notificationsPage = ref(1);
const expandedId = ref<number | null>(null);
const deletingNotificationId = ref<number | null>(null);
const bulkDeletingNotifications = ref(false);

const currentUserId = computed(() => auth.user?.id ?? null);

useNotificationsSocket({
  userId: currentUserId,
  onCreated: ({ notification, unreadCount: count }) => {
    if (notifications.value.some((n) => n.id === notification.id)) return;
    notifications.value = [notification, ...notifications.value];
    unreadCount.value = count;
  },
  onDeleted: ({ ids, unreadCount: count }) => {
    if (ids.length === 0) return;
    const removed = new Set(ids);
    notifications.value = notifications.value.filter((n) => !removed.has(n.id));
    unreadCount.value = count;
  },
  onRead: ({ id, readAt, unreadCount: count }) => {
    const idx = notifications.value.findIndex((n) => n.id === id);
    if (idx !== -1 && !notifications.value[idx].read_at) {
      notifications.value[idx] = { ...notifications.value[idx], read_at: readAt };
    }
    unreadCount.value = count;
  },
});

const pollsDrawerOpen = ref(false);
const polls = ref<UserPollItem[]>([]);
const loadingPolls = ref(false);

const userGuildIds = computed<number[]>(() => auth.user?.guild_ids ?? []);
const hasUserGuilds = computed(() => userGuildIds.value.length > 0);

let pollRefreshTimer: ReturnType<typeof setTimeout> | null = null;
function scheduleUserPollsReload() {
  if (!auth.isAuthenticated) return;
  if (pollRefreshTimer !== null) return;
  pollRefreshTimer = setTimeout(() => {
    pollRefreshTimer = null;
    if (auth.isAuthenticated) void loadPolls();
  }, 150);
}

useGuildPollsSocket({
  guildIds: userGuildIds,
  onChanged: ({ guildId, pollId }) => {
    const gid = Number(guildId);
    const pid = Number(pollId);
    if (!Number.isFinite(gid) || gid <= 0) return;
    if (!Number.isFinite(pid) || pid <= 0) return;
    scheduleUserPollsReload();
  },
  onDeleted: ({ guildId, pollId }) => {
    const gid = Number(guildId);
    const pid = Number(pollId);
    if (!Number.isFinite(gid) || gid <= 0) return;
    if (!Number.isFinite(pid) || pid <= 0) return;
    polls.value = polls.value.filter((p) => !(p.guild_id === gid && p.id === pid));
  },
});

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
  if (!hasUserGuilds.value) {
    polls.value = [];
    loadingPolls.value = false;
    return;
  }
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

watch(hasUserGuilds, (has) => {
  if (!has) {
    pollsDrawerOpen.value = false;
    polls.value = [];
  } else if (auth.isAuthenticated) {
    loadPolls();
  }
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

async function deleteNotificationsMany(ids: number[]) {
  if (bulkDeletingNotifications.value) return;
  const clean = ids.filter((id) => Number.isFinite(id) && id > 0);
  if (clean.length === 0) return;
  bulkDeletingNotifications.value = true;
  try {
    const removed = await notificationsApi.deleteMany(clean);
    const removedSet = new Set(removed.length > 0 ? removed : clean);
    notifications.value = notifications.value.filter((x) => !removedSet.has(x.id));
    unreadCount.value = notifications.value.filter((x) => !x.read_at).length;
  } catch {
    // ignore
  } finally {
    bulkDeletingNotifications.value = false;
  }
}

const themeOptions: { value: ThemePreference; label: string }[] = [
  { value: 'light', label: 'Светлая' },
  { value: 'dark', label: 'Тёмная' },
  { value: 'system', label: 'Системная' },
];

function setTheme(value: ThemePreference) {
  void nextTick(() => {
    theme.setPreference(value);
  });
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

function getMainSiteOrigin(): string {
  const fromEnv = import.meta.env.VITE_SITE_URL as string | undefined;
  if (fromEnv && /^https?:\/\//i.test(fromEnv.trim())) {
    return fromEnv.trim().replace(/\/$/, '');
  }
  if (typeof window === 'undefined') return DEFAULT_PRODUCTION_ORIGIN;
  const { protocol, hostname } = window.location;
  const parts = hostname.split('.');
  const baseHost = parts.length >= 3 ? parts.slice(1).join('.') : hostname;
  return `${protocol}//${baseHost}`;
}

const mainSiteHref = computed(() => `${getMainSiteOrigin()}/`);

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
      class="flex h-14 items-center justify-between gap-2 px-4 md:grid md:grid-cols-[1fr_auto_1fr] md:items-center md:gap-4 md:px-6"
    >
      <div class="flex min-w-0 shrink-0 items-center gap-2 md:justify-self-start">
        <a :href="mainSiteHref" class="group flex shrink-0 items-center gap-2 font-semibold">
          <SiteLogo :size="36" class="transition-transform group-hover:scale-105" />
        </a>
        <div class="shrink-0 md:hidden">
          <slot name="sidebar-trigger" />
        </div>
      </div>

      <nav
        class="hidden min-w-0 items-center justify-center gap-1 md:flex md:justify-self-center"
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

      <div class="flex min-w-0 items-center justify-end gap-2 md:flex-none md:justify-self-end">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-9 w-9" aria-label="Тема оформления" title="Тема оформления">
              <span class="relative inline-flex h-[1.125rem] w-[1.125rem] shrink-0 items-center justify-center">
                <svg v-show="theme.preference === 'light'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute inset-0 m-auto h-[1.125rem] w-[1.125rem]">
                  <circle cx="12" cy="12" r="4"/>
                  <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                </svg>
                <svg v-show="theme.preference === 'dark'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute inset-0 m-auto h-[1.125rem] w-[1.125rem]">
                  <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                </svg>
                <svg v-show="theme.preference === 'system'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute inset-0 m-auto h-[1.125rem] w-[1.125rem]">
                  <rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>
                </svg>
              </span>
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
        <template v-if="auth.isAuthenticated && hasUserGuilds">
          <PollsDrawer
            v-model:open="pollsDrawerOpen"
            :polls="polls"
            :loading="loadingPolls"
            :unvoted-count="unvotedPollsCount"
            @poll-updated="onPollUpdated"
          />
        </template>
        <!-- События сегодня (только для авторизованных) -->
        <template v-if="auth.isAuthenticated && hasUserGuilds">
          <TodaysEventsDrawer />
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
            :bulk-deleting="bulkDeletingNotifications"
            :timezone="auth.user?.timezone"
            @load="loadNotifications"
            @load-more="loadMoreNotifications"
            @notification-click="onNotificationClick"
            @notification-mouse-enter="onNotificationMouseEnter"
            @delete="deleteNotification"
            @delete-many="deleteNotificationsMany"
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
            <Button variant="ghost" size="icon" aria-label="Открыть меню" title="Меню" class="md:hidden">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>
              </svg>
            </Button>
          </template>
          <template v-if="hasMobileMenuSidebar" #toolbar-start>
            {{ mobileSheetContextTitle }}
          </template>
          <div
            class="flex min-h-0 flex-1 flex-col gap-4"
            :class="{ 'pt-8': !hasMobileMenuSidebar }"
          >
            <div class="flex shrink-0 flex-col">
              <RouterLink
                v-for="item in navItems"
                :key="item.to"
                :to="item.to"
                class="rounded-md px-3 py-2 text-base font-medium transition-colors hover:bg-muted/50"
                :class="
                  isNavActive(item.to)
                    ? 'bg-muted text-foreground'
                    : 'text-muted-foreground'
                "
                :aria-current="isNavActive(item.to) ? 'page' : undefined"
                @click="mobileMenuOpen = false"
              >
                {{ item.label }}
              </RouterLink>
            </div>
            <template v-if="hasMobileMenuSidebar">
              <Separator class="shrink-0" />
              <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                <slot name="mobile-menu-sidebar" :close-menu="closeMobileMenu" />
              </div>
            </template>
          </div>
        </Sheet>
      </div>
    </div>
  </header>
</template>
