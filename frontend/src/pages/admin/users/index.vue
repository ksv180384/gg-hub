<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import {
  Badge,
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
  TableSortHead,
} from '@/shared/ui';
import { accessApi, type AdminUserDto } from '@/shared/api/accessApi';
import { formatDateTimeFull } from '@/shared/lib/relativeTime';

const users = ref<AdminUserDto[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

function formatUserDate(value: string | null | undefined): string {
  return formatDateTimeFull(value) || 'Нет данных';
}

function formatUserRoles(roles: AdminUserDto['roles']): string {
  return roles.length ? roles.map((role) => role.name).join(', ') : 'Без роли';
}

type SortKey = 'user' | 'created_at' | 'last_activity_at' | 'roles' | 'status';
type SortDirection = 'asc' | 'desc';
type SortValue = string | number | null;

const sortKey = ref<SortKey>('created_at');
const sortDirection = ref<SortDirection>('desc');
const userCollator = new Intl.Collator('ru-RU', {
  numeric: true,
  sensitivity: 'base',
});

function setSort(key: SortKey) {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortKey.value = key;
  sortDirection.value = 'asc';
}

function parseSortDate(value: string | null | undefined): number | null {
  if (!value) return null;
  const timestamp = Date.parse(value);
  return Number.isNaN(timestamp) ? null : timestamp;
}

function getUserSortValue(user: AdminUserDto, key: SortKey): SortValue {
  if (key === 'user') return [user.name, user.email].join(' ');
  if (key === 'created_at') return parseSortDate(user.created_at);
  if (key === 'last_activity_at') return parseSortDate(user.last_activity_at);
  if (key === 'roles') return formatUserRoles(user.roles);
  return user.banned_at ? 'Заблокирован' : 'Активен';
}

function compareSortValues(left: SortValue, right: SortValue): number {
  if (left === null && right === null) return 0;
  if (left === null) return 1;
  if (right === null) return -1;
  if (typeof left === 'number' && typeof right === 'number') return left - right;
  return userCollator.compare(String(left), String(right));
}

const sortedUsers = computed(() =>
  [...users.value].sort((leftUser, rightUser) => {
    const leftValue = getUserSortValue(leftUser, sortKey.value);
    const rightValue = getUserSortValue(rightUser, sortKey.value);

    if (leftValue === null && rightValue === null) return 0;
    if (leftValue === null) return 1;
    if (rightValue === null) return -1;

    const result = compareSortValues(leftValue, rightValue);
    return sortDirection.value === 'asc' ? result : -result;
  }),
);

onMounted(async () => {
  try {
    users.value = await accessApi.getUsers();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="container py-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Пользователи</h1>
    </div>
    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <div v-else-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <div v-else class="rounded-xl border bg-card shadow-sm">
      <Table>
        <TableHeader
          class="[&_th]:sticky [&_th]:top-0 md:[&_th]:top-14 [&_th]:z-10 [&_th]:border-b [&_th]:bg-muted/95 [&_th]:backdrop-blur"
        >
          <TableRow class="hover:bg-transparent">
            <TableSortHead
              :active="sortKey === 'user'"
              :direction="sortDirection"
              @click="setSort('user')"
            >
              Пользователь
            </TableSortHead>
            <TableSortHead
              class="hidden whitespace-nowrap md:table-cell"
              :active="sortKey === 'created_at'"
              :direction="sortDirection"
              @click="setSort('created_at')"
            >
              Дата регистрации
            </TableSortHead>
            <TableSortHead
              class="hidden whitespace-nowrap sm:table-cell"
              :active="sortKey === 'last_activity_at'"
              :direction="sortDirection"
              @click="setSort('last_activity_at')"
            >
              Последнее посещение
            </TableSortHead>
            <TableSortHead
              class="hidden lg:table-cell"
              :active="sortKey === 'roles'"
              :direction="sortDirection"
              @click="setSort('roles')"
            >
              Роли
            </TableSortHead>
            <TableSortHead
              :active="sortKey === 'status'"
              :direction="sortDirection"
              @click="setSort('status')"
            >
              Статус
            </TableSortHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="u in sortedUsers"
            :key="u.id"
            class="odd:bg-background even:bg-muted/35"
          >
            <TableCell class="max-w-48 sm:min-w-56">
              <RouterLink
                :to="'/admin/users/' + u.id"
                class="font-medium text-foreground hover:underline"
              >
                {{ u.name }}
              </RouterLink>
              <p class="mt-0.5 truncate text-xs text-muted-foreground">
                {{ u.email }}
              </p>
            </TableCell>
            <TableCell class="hidden whitespace-nowrap md:table-cell">
              {{ formatUserDate(u.created_at) }}
            </TableCell>
            <TableCell class="hidden whitespace-nowrap sm:table-cell">
              {{ formatUserDate(u.last_activity_at) }}
            </TableCell>
            <TableCell class="hidden min-w-40 lg:table-cell">
              {{ formatUserRoles(u.roles) }}
            </TableCell>
            <TableCell>
              <Badge v-if="u.banned_at" variant="destructive">
                Заблокирован
              </Badge>
              <Badge v-else variant="outline">
                Активен
              </Badge>
            </TableCell>
          </TableRow>
          <TableRow v-if="users.length === 0">
            <TableCell
              colspan="5"
              class="h-24 text-center text-muted-foreground"
            >
              Пользователи не найдены
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>
