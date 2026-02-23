<script setup lang="ts">
import {
  Button,
  Input,
  Label,
  MultiSelect,
  Select,
  Spinner,
  type SelectOption,
} from '@/shared/ui';
import GuildCard from './GuildCard.vue';
import { useSiteContextStore } from '@/stores/siteContext';
import { useAuthStore } from '@/stores/auth';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { gamesApi, type Game, type Localization, type Server } from '@/shared/api/gamesApi';
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const siteContext = useSiteContextStore();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const ALL_GAMES_VALUE = '__all__';

// Фильтр
const filterName = ref('');
const filterGameId = ref(ALL_GAMES_VALUE);
const filterLocalizationIds = ref<number[]>([]);
const filterServerIds = ref<number[]>([]);

const guilds = ref<Guild[]>([]);
const memberGuildIds = ref<Set<number>>(new Set());
const loading = ref(true);
const refreshing = ref(false);
const error = ref<string | null>(null);

// Данные для фильтра: при субдомене игры — одна игра; без субдомена — список игр и выбранная игра с локалями/серверами
const games = ref<Game[]>([]);
const gameDetail = ref<Game | null>(null);
const loadingFilterOptions = ref(false);

const isGameSubdomain = computed(() => !!siteContext.game?.id);

/** Локализации для фильтра (из загруженной игры). */
const filterLocalizations = computed<Localization[]>(() => {
  const list = gameDetail.value?.localizations?.filter((l) => l.is_active !== false) ?? [];
  return list;
});

/** Серверы для выбранных локализаций (объединённый список). */
const filterServers = computed<Server[]>(() => {
  const locIds = new Set(filterLocalizationIds.value);
  if (locIds.size === 0) return [];
  const locs = filterLocalizations.value.filter((l) => locIds.has(l.id));
  const servers: Server[] = [];
  const seen = new Set<number>();
  for (const loc of locs) {
    for (const s of loc.servers ?? []) {
      if (!seen.has(s.id)) {
        seen.add(s.id);
        servers.push(s);
      }
    }
  }
  return servers.sort((a, b) => a.name.localeCompare(b.name));
});

const gameOptions = computed<SelectOption[]>(() => [
  { value: ALL_GAMES_VALUE, label: 'Все игры' },
  ...games.value.map((g) => ({ value: String(g.id), label: g.name })),
]);

const localizationMultiOptions = computed(() =>
  filterLocalizations.value.map((l) => ({ value: l.id, label: l.name }))
);
const serverMultiOptions = computed(() =>
  filterServers.value.map((s) => ({ value: s.id, label: s.name }))
);

function onLocalizationsChange(value: (string | number)[]) {
  filterLocalizationIds.value = value.map(Number);
  const serverIdSet = new Set(filterServers.value.map((s) => s.id));
  filterServerIds.value = filterServerIds.value.filter((id) => serverIdSet.has(id));
}
function onServersChange(value: (string | number)[]) {
  filterServerIds.value = value.map(Number);
}

/** Сбросить все поля фильтра. */
function resetFilter() {
  filterName.value = '';
  filterGameId.value = ALL_GAMES_VALUE;
  filterLocalizationIds.value = [];
  filterServerIds.value = [];
}

/** Выбор локализации по умолчанию: ru → eu → первая. */
function defaultLocalizationId(localizations: Localization[]): number | null {
  if (!localizations.length) return null;
  const byCode = (code: string) => localizations.find((l) => l.code?.toLowerCase() === code);
  return byCode('ru')?.id ?? byCode('eu')?.id ?? localizations[0]?.id ?? null;
}

/** Восстановить фильтр из query. */
function applyFilterFromQuery() {
  const q = route.query;
  if (typeof q.name === 'string') filterName.value = q.name;
  if (!isGameSubdomain.value) {
    if (typeof q.game_id === 'string' && q.game_id.trim() !== '') {
      filterGameId.value = q.game_id.trim();
    }
  }
  if (Array.isArray(q.localization_ids)) {
    filterLocalizationIds.value = q.localization_ids.map((id) => Number(id)).filter((id) => !Number.isNaN(id));
  } else if (typeof q.localization_ids === 'string' && q.localization_ids.trim() !== '') {
    filterLocalizationIds.value = q.localization_ids
      .split(',')
      .map((id) => Number(id.trim()))
      .filter((id) => !Number.isNaN(id));
  }
  if (Array.isArray(q.server_ids)) {
    filterServerIds.value = q.server_ids.map((id) => Number(id)).filter((id) => !Number.isNaN(id));
  } else if (typeof q.server_ids === 'string' && q.server_ids.trim() !== '') {
    filterServerIds.value = q.server_ids
      .split(',')
      .map((id) => Number(id.trim()))
      .filter((id) => !Number.isNaN(id));
  }
}

/** Записать текущий фильтр в URL (replace). */
function syncFilterToQuery() {
  const query: Record<string, string> = {};
  if (filterName.value.trim()) query.name = filterName.value.trim();
  if (!isGameSubdomain.value) {
    if (filterGameId.value && filterGameId.value !== ALL_GAMES_VALUE) {
      query.game_id = filterGameId.value;
    }
  }
  if (filterLocalizationIds.value.length) {
    query.localization_ids = filterLocalizationIds.value.join(',');
  }
  if (filterServerIds.value.length) {
    query.server_ids = filterServerIds.value.join(',');
  }
  router.replace({ query: Object.keys(query).length ? query : {} });
}

/** Загрузить данные для фильтра (игра с локалями и серверами). */
async function loadFilterOptions() {
  loadingFilterOptions.value = true;
  try {
    if (isGameSubdomain.value && siteContext.game?.id) {
      gameDetail.value = await gamesApi.getGame(siteContext.game.id);
      const defId = defaultLocalizationId(filterLocalizations.value);
      if (defId != null && filterLocalizationIds.value.length === 0) {
        filterLocalizationIds.value = [defId];
      }
      // Оставить только локализации/серверы, существующие в этой игре
      const locIds = new Set(filterLocalizations.value.map((l) => l.id));
      filterLocalizationIds.value = filterLocalizationIds.value.filter((id) => locIds.has(id));
      const serverIds = new Set(
        filterLocalizations.value.flatMap((l) => (l.servers ?? []).map((s) => s.id))
      );
      filterServerIds.value = filterServerIds.value.filter((id) => serverIds.has(id));
    } else {
      games.value = await gamesApi.getGames();
      const gameId =
        filterGameId.value && filterGameId.value !== ALL_GAMES_VALUE
          ? Number(filterGameId.value)
          : null;
      if (gameId) {
        gameDetail.value = await gamesApi.getGame(gameId);
        const defId = defaultLocalizationId(filterLocalizations.value);
        if (defId != null && filterLocalizationIds.value.length === 0) {
          filterLocalizationIds.value = [defId];
        }
        // Оставить только локализации/серверы, существующие в выбранной игре
        const locIds = new Set(filterLocalizations.value.map((l) => l.id));
        filterLocalizationIds.value = filterLocalizationIds.value.filter((id) => locIds.has(id));
        const serverIds = new Set(
          filterLocalizations.value.flatMap((l) => (l.servers ?? []).map((s) => s.id))
        );
        filterServerIds.value = filterServerIds.value.filter((id) => serverIds.has(id));
      } else {
        gameDetail.value = null;
      }
    }
  } catch {
    gameDetail.value = null;
  } finally {
    loadingFilterOptions.value = false;
  }
}

async function loadMemberGuildIds() {
  const game = siteContext.game;
  if (!authStore.isAuthenticated || !game?.id) {
    memberGuildIds.value = new Set();
    return;
  }
  try {
    const list = await guildsApi.getMyGuildsForGame(game.id);
    memberGuildIds.value = new Set(list.map((g) => g.id));
  } catch {
    memberGuildIds.value = new Set();
  }
}

async function loadGuilds(showFullLoading = false) {
  if (showFullLoading) {
    loading.value = true;
  } else {
    refreshing.value = true;
  }
  error.value = null;
  try {
    const params: {
      per_page: number;
      game_id?: number;
      name?: string;
      localization_ids?: number[];
      server_ids?: number[];
    } = { per_page: 50 };

    if (isGameSubdomain.value && siteContext.game?.id) {
      params.game_id = siteContext.game.id;
    } else if (
      filterGameId.value &&
      filterGameId.value !== ALL_GAMES_VALUE
    ) {
      params.game_id = Number(filterGameId.value);
    }
    if (filterName.value.trim()) params.name = filterName.value.trim();
    if (filterLocalizationIds.value.length) params.localization_ids = [...filterLocalizationIds.value];
    if (filterServerIds.value.length) params.server_ids = [...filterServerIds.value];

    const { guilds: list } = await guildsApi.getGuilds(params);
    guilds.value = list;
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить гильдии';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

const filterReady = ref(false);

onMounted(async () => {
  applyFilterFromQuery();
  await loadFilterOptions();
  await loadGuilds(true);
  loadMemberGuildIds();
  filterReady.value = true;
});

let loadGuildsTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
  [filterName, filterGameId, filterLocalizationIds, filterServerIds],
  () => {
    if (!filterReady.value) return;
    if (loadGuildsTimeout) clearTimeout(loadGuildsTimeout);
    loadGuildsTimeout = setTimeout(() => {
      syncFilterToQuery();
      loadGuilds();
    }, 350);
  },
  { deep: true }
);

watch(() => siteContext.game?.id, () => {
  loadMemberGuildIds();
});

watch(filterGameId, async () => {
  filterLocalizationIds.value = [];
  filterServerIds.value = [];
  const gameId =
    filterGameId.value && filterGameId.value !== ALL_GAMES_VALUE
      ? Number(filterGameId.value)
      : null;
  if (gameId) {
    try {
      gameDetail.value = await gamesApi.getGame(gameId);
      const defId = defaultLocalizationId(filterLocalizations.value);
      if (defId != null) filterLocalizationIds.value = [defId];
    } catch {
      gameDetail.value = null;
    }
  } else {
    gameDetail.value = null;
  }
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-5xl">
      <div class="mb-2 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-bold tracking-tight">Гильдии</h1>
        <Button v-if="authStore.isAuthenticated" @click="router.push({ name: 'guilds-create' })">
          Создать гильдию
        </Button>
      </div>
      <p class="mb-6 text-muted-foreground">
        <template v-if="siteContext.game">
          Гильдии игры {{ siteContext.game.name }}. Найдите гильдию или создайте свою.
        </template>
        <template v-else>
          Найдите гильдию под свой стиль игры или создайте свою.
        </template>
      </p>

      <!-- Компактный фильтр -->
      <div class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 px-4 py-3">
        <div class="flex min-w-0 flex-1 flex-col gap-1 sm:min-w-[180px]">
          <Label for="guild-filter-name" class="text-xs text-muted-foreground">Название</Label>
          <Input
            id="guild-filter-name"
            v-model="filterName"
            placeholder="Поиск по названию..."
            class="h-8 text-sm"
          />
        </div>
        <template v-if="!isGameSubdomain">
          <div class="flex min-w-0 flex-col gap-1 sm:min-w-[140px]">
            <Label for="guild-filter-game" class="text-xs text-muted-foreground">Игра</Label>
            <Select
              id="guild-filter-game"
              v-model="filterGameId"
              :options="gameOptions"
              placeholder="Игра"
              :disabled="loadingFilterOptions || !games.length"
              trigger-class="h-8 text-sm w-full"
            />
          </div>
        </template>
        <div class="flex flex-col gap-1">
          <Label class="text-xs text-muted-foreground">Локализация</Label>
          <MultiSelect
            :model-value="filterLocalizationIds"
            :options="localizationMultiOptions"
            placeholder="Все"
            search-placeholder="Поиск локализации..."
            empty-text="Нет локализаций"
            :disabled="loadingFilterOptions || !filterLocalizations.length"
            trigger-class="min-w-[140px]"
            @update:model-value="onLocalizationsChange"
          />
        </div>
        <div class="flex flex-col gap-1">
          <Label class="text-xs text-muted-foreground">Сервер</Label>
          <MultiSelect
            :model-value="filterServerIds"
            :options="serverMultiOptions"
            placeholder="Все"
            search-placeholder="Поиск сервера..."
            empty-text="Нет серверов"
            :disabled="!filterServers.length"
            trigger-class="min-w-[140px]"
            @update:model-value="onServersChange"
          />
        </div>
        <Button
          variant="outline"
          size="icon"
          class="h-8 w-8 shrink-0"
          title="Сбросить фильтр"
          aria-label="Сбросить фильтр"
          @click="resetFilter"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
          </svg>
        </Button>
      </div>

      <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ error }}
      </div>

      <!-- Область вывода карточек: прелоадер в верхнем правом углу -->
      <div class="relative min-h-[280px]">
        <!-- Мини-прелоадер в верхнем правом углу -->
        <div
          v-if="loading || refreshing"
          class="absolute left-1/2 top-2 z-10 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-muted/70 px-2 py-0.5"
          aria-busy="true"
          aria-live="polite"
        >
          <Spinner class="h-1.5 w-1.5 shrink-0 text-muted-foreground" />
          <span class="text-xs text-muted-foreground">Загрузка…</span>
        </div>

        <div
          v-if="loading && guilds.length === 0"
          class="pt-10"
        >
          <!-- Пустое место при первой загрузке, карточки появятся после -->
        </div>

        <div
          v-else-if="guilds.length === 0"
          class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
        >
          По выбранным критериям гильдий не найдено.
          <template v-if="authStore.isAuthenticated"> Создайте свою.</template>
        </div>

        <div
          v-else
          class="grid justify-items-center gap-6 pt-8 sm:grid-cols-2 sm:justify-items-stretch lg:grid-cols-3"
        >
        <GuildCard
          v-for="(g, i) in guilds"
          :key="g.id"
          :guild="g"
          list-mode
          :show-game-name="!isGameSubdomain"
          class="animate-in fade-in slide-in-from-bottom-3"
            :style="{ animationDelay: `${i * 80}ms`, animationDuration: '400ms', animationFillMode: 'backwards' }"
          />
        </div>
      </div>
    </div>
  </div>
</template>
