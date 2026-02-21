<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { PERMISSION_ACCESS_ADMIN } from '@/shared/api/authApi';
import { cn } from '@/shared/lib/utils';

const route = useRoute();
const auth = useAuthStore();
const siteContext = useSiteContextStore();
const managementOpen = ref(true);

const navItems = [
  { to: '/characters', label: 'Персонажи' },
  { to: '/guild', label: 'Гильдия' },
];

const adminItems = [
  { to: '/admin/games', label: 'Игры' },
  { to: '/admin/users', label: 'Пользователи' },
  { to: '/admin/roles', label: 'Роли пользователей' },
  { to: '/admin/permissions', label: 'Права пользователей' },
  { to: '/admin/permission-groups', label: 'Категории прав' },
];

const showAdminBlock = computed(
  () => siteContext.isAdmin && auth.hasPermission(PERMISSION_ACCESS_ADMIN)
);

const sidebarTitle = computed(() => {
  if (showAdminBlock.value) return 'Админ';
  return siteContext.game?.name ?? 'Игра';
});

const isAdminRoute = (path: string) =>
  route.path === path || route.path.startsWith(path + '/');
</script>

<template>
  <div class="flex h-full flex-col bg-[var(--sidebar)] text-[var(--sidebar-foreground)]">
    <div class="flex h-14 shrink-0 items-center gap-2 border-b border-[var(--sidebar-border)] px-4">
      <span class="font-semibold">{{ sidebarTitle }}</span>
    </div>
    <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-2">
      <template v-if="siteContext.isGameSubdomain">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :class="cn(
            'rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]',
            route.path === item.to
              ? 'bg-[var(--sidebar-accent)] text-[var(--sidebar-accent-foreground)]'
              : ''
          )"
        >
          {{ item.label }}
        </RouterLink>
      </template>

      <template v-if="showAdminBlock">
        <div class="mt-2">
          <button
            type="button"
            class="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]"
            :class="{ 'bg-[var(--sidebar-accent)]/50': managementOpen }"
            :aria-expanded="managementOpen"
            @click="managementOpen = !managementOpen"
          >
            <span>Управление</span>
            <span
              class="inline-block text-[10px] transition-transform duration-200"
              :class="managementOpen ? '' : '-rotate-90'"
              aria-hidden
            >
              ▼
            </span>
          </button>
          <Transition
            enter-active-class="transition-all duration-200 ease-out overflow-hidden"
            enter-from-class="opacity-0 -translate-y-2 max-h-0"
            enter-to-class="opacity-100 translate-y-0 max-h-40"
            leave-active-class="transition-all duration-200 ease-in overflow-hidden"
            leave-from-class="opacity-100 translate-y-0 max-h-40"
            leave-to-class="opacity-0 -translate-y-2 max-h-0"
          >
            <div
              v-if="managementOpen"
              class="ml-2 mt-1 flex flex-col gap-0.5 border-l border-[var(--sidebar-border)] pl-2"
            >
              <RouterLink
                v-for="item in adminItems"
                :key="item.to"
                :to="item.to"
                :class="cn(
                  'rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-[var(--sidebar-accent)] hover:text-[var(--sidebar-accent-foreground)]',
                  isAdminRoute(item.to)
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
    </nav>
  </div>
</template>
