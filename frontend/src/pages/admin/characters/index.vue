<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { X } from '@lucide/vue';
import { useRouter } from 'vue-router';
import {
  Button,
  Input,
  SearchableSelect,
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
  TableSortHead,
  type SearchableSelectOption,
} from '@/shared/ui';
import { gamesApi, type GameCatalogItem, type Server } from '@/shared/api/gamesApi';
import {
  adminCharactersApi,
  type AdminCharacterDto,
  type AdminCharactersMeta,
  type AdminCharacterSort,
  type SortDirection,
} from '@/shared/api/adminCharactersApi';

const router = useRouter();
const characters = ref<AdminCharacterDto[]>([]);
const meta = ref<AdminCharactersMeta | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const filters = reactive({
  name: '',
  email: '',
});
const games = ref<GameCatalogItem[]>([]);
const servers = ref<Server[]>([]);
const selectedGameId = ref<number | null>(null);
const selectedServerId = ref<number | null>(null);
const gamesLoading = ref(false);
const serversLoading = ref(false);
const sortKey = ref<AdminCharacterSort>('name');
const sortDirection = ref<SortDirection>('asc');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let serverRequestId = 0;

const currentPage = computed(() => meta.value?.current_page ?? 1);
const lastPage = computed(() => meta.value?.last_page ?? 1);
const total = computed(() => meta.value?.total ?? 0);
const gameOptions = computed<SearchableSelectOption[]>(() =>
  games.value.map((game) => ({
    value: game.id,
    label: game.name,
  })),
);
const serverOptions = computed<SearchableSelectOption[]>(() =>
  servers.value.map((server) => ({
    value: server.id,
    label: server.name,
  })),
);

async function loadCharacters(page = 1): Promise<void> {
  loading.value = true;
  error.value = null;

  try {
    const response = await adminCharactersApi.getCharacters({
      ...filters,
      game_id: selectedGameId.value,
      server_id: selectedServerId.value,
      sort: sortKey.value,
      direction: sortDirection.value,
      page,
    });

    characters.value = response.data;
    meta.value = response.meta;
  } catch (loadError) {
    characters.value = [];
    meta.value = null;
    error.value = loadError instanceof Error ? loadError.message : 'Ошибка загрузки персонажей';
  } finally {
    loading.value = false;
  }
}

async function loadGames(): Promise<void> {
  gamesLoading.value = true;

  try {
    games.value = await gamesApi.getGamesCatalog();
  } catch {
    games.value = [];
  } finally {
    gamesLoading.value = false;
  }
}

async function loadServersForGame(gameId: number): Promise<void> {
  const requestId = ++serverRequestId;
  serversLoading.value = true;

  try {
    const game = await gamesApi.getGame(gameId);

    if (requestId !== serverRequestId) {
      return;
    }

    const serversById = new Map<number, Server>();

    for (const localization of game.localizations ?? []) {
      for (const server of localization.servers ?? []) {
        serversById.set(server.id, server);
      }
    }

    servers.value = Array.from(serversById.values()).sort((left, right) =>
      left.name.localeCompare(right.name, 'ru-RU', {
        numeric: true,
        sensitivity: 'base',
      }),
    );
  } catch {
    if (requestId === serverRequestId) {
      servers.value = [];
    }
  } finally {
    if (requestId === serverRequestId) {
      serversLoading.value = false;
    }
  }
}

function setSort(key: AdminCharacterSort): void {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDirection.value = 'asc';
  }

  void loadCharacters(1);
}

function openUser(character: AdminCharacterDto): void {
  void router.push(`/admin/users/${character.user.id}`);
}

function clearTextFilter(key: 'name' | 'email'): void {
  filters[key] = '';
}

watch(selectedGameId, (gameId) => {
  selectedServerId.value = null;
  servers.value = [];
  serverRequestId += 1;
  serversLoading.value = false;

  if (gameId != null) {
    void loadServersForGame(gameId);
  }
});

watch([() => filters.name, () => filters.email, selectedGameId, selectedServerId], () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  searchTimeout = setTimeout(() => {
    searchTimeout = null;
    void loadCharacters(1);
  }, 300);
});

onMounted(() => {
  void loadGames();
  void loadCharacters();
});

onBeforeUnmount(() => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
});
</script>

<template>
  <div class="container py-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Персонажи</h1>
    </div>

    <div
      class="mb-4 grid gap-3 rounded-xl border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4"
    >
      <div class="relative">
        <Input
          v-model="filters.name"
          class="pr-9"
          placeholder="Поиск по нику"
          aria-label="Поиск по нику персонажа"
        />
        <Button
          v-if="filters.name"
          variant="ghost"
          size="icon"
          class="absolute right-0.5 top-0.5 h-8 w-8"
          aria-label="Очистить поиск по нику"
          @click="clearTextFilter('name')"
        >
          <X />
        </Button>
      </div>
      <div class="relative">
        <Input
          v-model="filters.email"
          class="pr-9"
          placeholder="Поиск по email"
          aria-label="Поиск по email пользователя"
        />
        <Button
          v-if="filters.email"
          variant="ghost"
          size="icon"
          class="absolute right-0.5 top-0.5 h-8 w-8"
          aria-label="Очистить поиск по email"
          @click="clearTextFilter('email')"
        >
          <X />
        </Button>
      </div>
      <SearchableSelect
        v-model="selectedGameId"
        :options="gameOptions"
        :loading="gamesLoading"
        placeholder="Все игры"
        search-placeholder="Поиск по играм..."
        empty-text="Игры не найдены"
        clear-label="Все игры"
        aria-label="Фильтр по игре"
      />
      <SearchableSelect
        v-model="selectedServerId"
        :options="serverOptions"
        :disabled="selectedGameId == null"
        :loading="serversLoading"
        :placeholder="selectedGameId == null ? 'Сначала выберите игру' : 'Все серверы'"
        search-placeholder="Поиск по серверам..."
        empty-text="Серверы не найдены"
        clear-label="Все серверы"
        aria-label="Фильтр по серверу"
      />
    </div>

    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <div v-else class="rounded-xl border bg-card shadow-sm">
      <Table>
        <TableHeader
          class="[&_th]:sticky [&_th]:top-0 md:[&_th]:top-14 [&_th]:z-10 [&_th]:border-b [&_th]:bg-muted/95 [&_th]:backdrop-blur"
        >
          <TableRow class="hover:bg-transparent">
            <TableSortHead
              :active="sortKey === 'name'"
              :direction="sortDirection"
              @click="setSort('name')"
            >
              Ник персонажа
            </TableSortHead>
            <TableSortHead
              :active="sortKey === 'email'"
              :direction="sortDirection"
              @click="setSort('email')"
            >
              Email пользователя
            </TableSortHead>
            <TableSortHead
              :active="sortKey === 'game'"
              :direction="sortDirection"
              @click="setSort('game')"
            >
              Игра
            </TableSortHead>
            <TableSortHead
              :active="sortKey === 'server'"
              :direction="sortDirection"
              @click="setSort('server')"
            >
              Сервер
            </TableSortHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="loading">
            <TableCell colspan="4" class="h-24 text-center text-muted-foreground">
              Загрузка…
            </TableCell>
          </TableRow>
          <TableRow
            v-for="character in characters"
            v-else
            :key="character.id"
            class="cursor-pointer odd:bg-background even:bg-muted/35"
            role="link"
            tabindex="0"
            @click="openUser(character)"
            @keydown.enter="openUser(character)"
          >
            <TableCell class="font-medium">{{ character.name }}</TableCell>
            <TableCell>{{ character.user.email }}</TableCell>
            <TableCell>{{ character.game.name }}</TableCell>
            <TableCell>{{ character.server.name }}</TableCell>
          </TableRow>
          <TableRow v-if="!loading && characters.length === 0">
            <TableCell colspan="4" class="h-24 text-center text-muted-foreground">
              Персонажи не найдены
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div v-if="meta && lastPage > 1" class="flex items-center justify-center gap-2 pt-4">
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage <= 1 || loading"
        @click="loadCharacters(currentPage - 1)"
      >
        Назад
      </Button>
      <span class="text-sm text-muted-foreground">
        {{ currentPage }} / {{ lastPage }} (всего {{ total }})
      </span>
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage >= lastPage || loading"
        @click="loadCharacters(currentPage + 1)"
      >
        Вперёд
      </Button>
    </div>
  </div>
</template>
