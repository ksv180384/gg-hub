<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import { useAdminJournalStore } from '@/stores/adminJournal';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { PERMISSION_ACCESS_ADMIN, PERMISSION_VIEW_POLLS } from '@/shared/api/authApi';
import { guildsApi, type UserGuildItem } from '@/shared/api/guildsApi';
import { cn } from '@/shared/lib/utils';

const SIDEBAR_STORAGE_KEY_GUILD_PREFIX = 'gg-sidebar-open-guild-';

const route = useRoute();
const auth = useAuthStore();
const siteContext = useSiteContextStore();

const userGuilds = ref<UserGuildItem[]>([]);
const guildsLoading = ref(false);
/** Множество id гильдий с раскрытым подменю (можно держать открытыми несколько). */
const openGuildIds = ref<Set<number>>(new Set());

const guildSubmenuItems: { pathSuffix: string; label: string }[] = [
  { pathSuffix: '/posts', label: 'Журнал гильдии' },
  { pathSuffix: '/settings', label: 'Информация' },
  { pathSuffix: '/roster', label: 'Состав' },
  { pathSuffix: '/raids', label: 'Рейды|Группы|КП' },
  { pathSuffix: '/applications', label: 'Заявки и приглашения' },
  { pathSuffix: '/calendar', label: 'Календарь событий' },
  { pathSuffix: '/events', label: 'События' },
  { pathSuffix: '/polls', label: 'Голосования' },
  { pathSuffix: '/auction', label: 'Аукцион' },
  { pathSuffix: '/roles', label: 'Роли членов гильдии' },
];

function guildPath(guildId: number, pathSuffix: string): string {
  return `/guilds/${guildId}${pathSuffix}`;
}

const adminJournal = useAdminJournalStore();

const adminItems: { to: string; label: string; permission?: string }[] = [
  { to: '/admin/journal', label: 'Журнал' },
  { to: '/admin/comments', label: 'Комментарии' },
  { to: '/admin/application-comments', label: 'Комментарии заявок' },
  { to: '/admin/polls', label: 'Голосования', permission: PERMISSION_VIEW_POLLS },
  { to: '/admin/games', label: 'Игры' },
  { to: '/admin/users', label: 'Пользователи' },
  { to: '/admin/roles', label: 'Роли пользователей' },
  { to: '/admin/permissions', label: 'Права пользователей' },
  { to: '/admin/permission-groups', label: 'Категории прав' },
  { to: '/admin/guild-permissions', label: 'Права гильдии' },
  { to: '/admin/tags', label: 'Теги' },
];

const showAdminBlock = computed(
  () =>
    siteContext.isAdmin &&
    (auth.hasPermission(PERMISSION_ACCESS_ADMIN) || auth.hasPermission(PERMISSION_VIEW_POLLS))
);

const visibleAdminItems = computed(() =>
  adminItems.filter((item) => {
    if (auth.hasPermission(PERMISSION_ACCESS_ADMIN)) return true;
    return item.permission && auth.hasPermission(item.permission);
  })
);

const sidebarTitle = computed(() => {
  if (showAdminBlock.value) return 'Админ';
  return siteContext.game?.name ?? 'Игра';
});

const isAdminRoute = (path: string) =>
  route.path === path || route.path.startsWith(path + '/');

function isGuildRoute(guildId: number, pathSuffix: string): boolean {
  if (pathSuffix === '') {
    const base = `/guilds/${guildId}`;
    return route.path === base || route.path === base + '/';
  }
  const path = guildPath(guildId, pathSuffix);
  return route.path === path || route.path.startsWith(path + '/');
}

async function loadAdminPendingCount() {
  if (!showAdminBlock.value) return;
  await adminJournal.refreshPendingCount();
}

async function loadUserGuilds() {
  const game = siteContext.game;
  if (!auth.isAuthenticated || !game?.id) {
    userGuilds.value = [];
    return;
  }
  guildsLoading.value = true;
  openGuildIds.value = new Set();
  try {
    userGuilds.value = await guildsApi.getMyGuildsForGame(game.id);
    const key = SIDEBAR_STORAGE_KEY_GUILD_PREFIX + game.id;
    try {
      const saved = localStorage.getItem(key);
      if (saved != null && saved !== '') {
        const ids = saved.split(',').map((s) => Number(s.trim())).filter(Number.isInteger);
        const valid = new Set(userGuilds.value.filter((g) => ids.includes(g.id)).map((g) => g.id));
        if (valid.size > 0) openGuildIds.value = valid;
      }
    } catch {
      /* ignore */
    }
  } catch {
    userGuilds.value = [];
  } finally {
    guildsLoading.value = false;
  }
}

function toggleGuild(guildId: number) {
  const next = new Set(openGuildIds.value);
  if (next.has(guildId)) next.delete(guildId);
  else next.add(guildId);
  openGuildIds.value = next;
  const game = siteContext.game;
  if (game?.id != null) {
    try {
      const key = SIDEBAR_STORAGE_KEY_GUILD_PREFIX + game.id;
      if (next.size > 0) localStorage.setItem(key, [...next].join(','));
      else localStorage.removeItem(key);
    } catch {
      /* ignore */
    }
  }
}

function isGuildOpen(guildId: number): boolean {
  return openGuildIds.value.has(guildId);
}

onMounted(() => {
  loadUserGuilds();
  loadAdminPendingCount();
});
watch(() => siteContext.game?.id, () => loadUserGuilds());
watch(showAdminBlock, (v) => v && loadAdminPendingCount(), { immediate: true });
</script>

<template>
  <div class="flex h-full flex-col bg-[var(--sidebar)] text-[var(--sidebar-foreground)]">
    <div class="flex h-14 shrink-0 items-center gap-2 border-b border-[var(--sidebar-border)] px-4">
      <span class="font-semibold">{{ sidebarTitle }}</span>
    </div>
    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-2">
      <template v-if="siteContext.isGameSubdomain">
        <RouterLink
          to="/journal"
          :class="cn(
            'rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]',
            route.path === '/journal' || route.path.startsWith('/journal/')
              ? 'bg-[var(--sidebar-accent)] text-[var(--sidebar-accent-foreground)]'
              : ''
          )"
        >
          Журнал
        </RouterLink>
        <RouterLink
          to="/characters"
          :class="cn(
            'rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]',
            route.path === '/characters' || route.path.startsWith('/characters/')
              ? 'bg-[var(--sidebar-accent)] text-[var(--sidebar-accent-foreground)]'
              : ''
          )"
        >
          Персонажи
        </RouterLink>
        <RouterLink
          to="/applications"
          :class="cn(
            'rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]',
            route.path === '/applications' || route.path.startsWith('/applications/')
              ? 'bg-[var(--sidebar-accent)] text-[var(--sidebar-accent-foreground)]'
              : ''
          )"
        >
          Заявки и приглашения
        </RouterLink>

        <template v-if="guildsLoading">
          <div class="px-3 py-2 text-sm text-muted-foreground">Загрузка гильдий…</div>
        </template>
        <template v-else-if="userGuilds.length > 0">
          <div v-for="guild in userGuilds" :key="guild.id" class="mt-1">
            <button
              type="button"
              class="flex w-full items-center justify-between gap-1 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]"
              :class="{ 'bg-[var(--sidebar-accent)]/50': isGuildOpen(guild.id) }"
              :aria-expanded="isGuildOpen(guild.id)"
              @click="toggleGuild(guild.id)"
            >
              <span class="flex min-w-0 items-center gap-1.5">
                <span class="truncate">
                  {{ userGuilds.length === 1 ? 'Гильдия' : guild.name }}
                </span>
                <svg
                  v-if="guild.is_leader"
                  xmlns="http://www.w3.org/2000/svg"
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="currentColor"
                  class="shrink-0 text-amber-500"
                  aria-label="Лидер"
                >
                  <path d="M2 17l2-7 4 3 4-6 4 6 4-3 2 7H2zm0 2h20v2H2v-2z" />
                </svg>
              </span>
              <span
                class="inline-block shrink-0 text-[10px] transition-transform duration-200"
                :class="isGuildOpen(guild.id) ? '' : '-rotate-90'"
                aria-hidden
              >
                ▼
              </span>
            </button>
            <Transition
              enter-active-class="transition-all duration-200 ease-out overflow-hidden"
              enter-from-class="opacity-0 -translate-y-2 max-h-0"
              enter-to-class="opacity-100 translate-y-0 max-h-[320px]"
              leave-active-class="transition-all duration-200 ease-in overflow-hidden"
              leave-from-class="opacity-100 translate-y-0 max-h-[320px]"
              leave-to-class="opacity-0 -translate-y-2 max-h-0"
            >
              <div
                v-if="isGuildOpen(guild.id)"
                class="ml-2 mt-1 flex flex-col gap-0.5 border-l border-[var(--sidebar-border)] pl-2"
              >
                <RouterLink
                  v-for="item in guildSubmenuItems"
                  :key="item.pathSuffix || 'journal'"
                  v-show="item.pathSuffix !== '/roles' || guild.can_access_roles"
                  :to="guildPath(guild.id, item.pathSuffix)"
                  :class="cn(
                    'rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]',
                    isGuildRoute(guild.id, item.pathSuffix)
                      ? 'bg-[var(--sidebar-accent)] text-[var(--sidebar-accent-foreground)]'
                      : ''
                  )"
                >
                  {{ item.label }}
                </RouterLink>
              </div>
            </Transition>
          </div>
        </template>
      </template>

      <template v-if="showAdminBlock">
        <RouterLink
          v-for="item in visibleAdminItems"
          :key="item.to"
          :to="item.to"
          :class="cn(
            'flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]',
            isAdminRoute(item.to)
              ? 'bg-[var(--sidebar-accent)] text-[var(--sidebar-accent-foreground)]'
              : ''
          )"
        >
          <span>{{ item.label }}</span>
          <span
            v-if="item.to === '/admin/journal' && adminJournal.pendingCount > 0"
            class="shrink-0 rounded-full bg-destructive/90 px-2 py-0.5 text-xs font-medium text-destructive-foreground"
          >
            {{ adminJournal.pendingCount }}
          </span>
        </RouterLink>
      </template>
    </nav>
  </div>
</template>
