<script setup lang="ts">
import {
  Button,
  Label,
  MultiSelect,
  Select,
  Spinner,
  type SelectOption,
} from '@/shared/ui';
import { ResponsiveFiltersToolbar } from '@/widgets/responsive-filters-toolbar';
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
const ALL_RECRUITING_VALUE = '__all__';

// Фильтр
const filterName = ref('');
const filterGameId = ref(ALL_GAMES_VALUE);
const filterLocalizationIds = ref<number[]>([]);
const filterServerIds = ref<number[]>([]);
const filterRecruiting = ref(ALL_RECRUITING_VALUE);
const filterTagIds = ref<number[]>([]);

const allGuilds = ref<Guild[]>([]);
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

const tagMultiOptions = computed<{ value: number; label: string }[]>(() => {
  const map = new Map<number, string>();
  for (const g of allGuilds.value) {
    for (const t of g.tags ?? []) {
      if (!map.has(t.id)) map.set(t.id, t.name);
    }
  }
  return [...map.entries()]
    .map(([id, name]) => ({ value: id, label: name }))
    .sort((a, b) => a.label.localeCompare(b.label));
});

const recruitingOptions = computed<SelectOption[]>(() => [
  { value: ALL_RECRUITING_VALUE, label: 'Любой' },
  { value: '1', label: 'Открыт' },
]);

const guildsExtraFiltersActive = computed(() => {
  const gameChosen =
    !isGameSubdomain.value &&
    filterGameId.value !== '' &&
    filterGameId.value !== ALL_GAMES_VALUE;
  return (
    gameChosen ||
    filterLocalizationIds.value.length > 0 ||
    filterServerIds.value.length > 0 ||
    filterRecruiting.value !== ALL_RECRUITING_VALUE ||
    filterTagIds.value.length > 0
  );
});

const guildsExtraFiltersUiTitle = computed(() =>
  isGameSubdomain.value
    ? 'Локализация, сервер, набор, теги'
    : 'Игра, локализация, сервер, набор, теги',
);

const guildsExtraFiltersUiAria = computed(() =>
  isGameSubdomain.value
    ? 'Открыть фильтры: локализация, сервер, набор, теги'
    : 'Открыть фильтры: игра, локализация, сервер, набор, теги',
);

function onLocalizationsChange(value: (string | number)[]) {
  filterLocalizationIds.value = value.map(Number);
  const serverIdSet = new Set(filterServers.value.map((s) => s.id));
  filterServerIds.value = filterServerIds.value.filter((id) => serverIdSet.has(id));
}
function onServersChange(value: (string | number)[]) {
  filterServerIds.value = value.map(Number);
}
function onTagsChange(value: (string | number)[]) {
  filterTagIds.value = value.map(Number);
}

const visibleGuilds = computed<Guild[]>(() => {
  if (!filterTagIds.value.length) return allGuilds.value;
  const selected = new Set(filterTagIds.value);
  return allGuilds.value.filter((g) => (g.tags ?? []).some((t) => selected.has(t.id)));
});

/** Ключ sessionStorage: отдельно для каталога всех игр и для субдомена конкретной игры. */
function guildsFilterStorageKey(): string {
  const gid = siteContext.game?.id;
  if (gid) return `gg:guilds-list-filter:game:${gid}`;
  return 'gg:guilds-list-filter:global';
}

type StoredGuildsListFilter = {
  name: string;
  gameId: string;
  localizationIds: number[];
  serverIds: number[];
  recruiting: string;
  tagIds: number[];
};

function hasGuildsFilterInQuery(q: typeof route.query): boolean {
  if (typeof q.name === 'string' && q.name.trim() !== '') return true;
  if (!isGameSubdomain.value && typeof q.game_id === 'string' && q.game_id.trim() !== '') return true;
  const loc = q.localization_ids;
  if (Array.isArray(loc) && loc.length > 0) return true;
  if (typeof loc === 'string' && loc.trim() !== '') return true;
  const srv = q.server_ids;
  if (Array.isArray(srv) && srv.length > 0) return true;
  if (typeof srv === 'string' && srv.trim() !== '') return true;
  if (typeof q.is_recruiting === 'string' && q.is_recruiting.trim() !== '') return true;
  const tags = q.tag_ids;
  if (Array.isArray(tags) && tags.length > 0) return true;
  if (typeof tags === 'string' && tags.trim() !== '') return true;
  return false;
}

function persistGuildsFilterToSession() {
  const payload: StoredGuildsListFilter = {
    name: filterName.value,
    gameId: filterGameId.value,
    localizationIds: [...filterLocalizationIds.value],
    serverIds: [...filterServerIds.value],
    recruiting: filterRecruiting.value,
    tagIds: [...filterTagIds.value],
  };
  try {
    sessionStorage.setItem(guildsFilterStorageKey(), JSON.stringify(payload));
  } catch {
    // квота или недоступность sessionStorage
  }
}

function applyGuildsFilterFromSession() {
  let raw: string | null = null;
  try {
    raw = sessionStorage.getItem(guildsFilterStorageKey());
  } catch {
    return;
  }
  if (!raw) return;
  try {
    const parsed = JSON.parse(raw) as Partial<StoredGuildsListFilter>;
    filterName.value = typeof parsed.name === 'string' ? parsed.name : '';
    if (!isGameSubdomain.value) {
      filterGameId.value =
        typeof parsed.gameId === 'string' && parsed.gameId !== '' ? parsed.gameId : ALL_GAMES_VALUE;
    }
    filterLocalizationIds.value = Array.isArray(parsed.localizationIds)
      ? parsed.localizationIds.map(Number).filter((id) => !Number.isNaN(id))
      : [];
    filterServerIds.value = Array.isArray(parsed.serverIds)
      ? parsed.serverIds.map(Number).filter((id) => !Number.isNaN(id))
      : [];
    filterRecruiting.value =
      typeof parsed.recruiting === 'string' && parsed.recruiting !== ''
        ? parsed.recruiting
        : ALL_RECRUITING_VALUE;
    filterTagIds.value = Array.isArray(parsed.tagIds)
      ? parsed.tagIds.map(Number).filter((id) => !Number.isNaN(id))
      : [];
  } catch {
    // невалидный JSON
  }
}

/** Сбросить все поля фильтра. */
function resetFilter() {
  filterName.value = '';
  filterGameId.value = ALL_GAMES_VALUE;
  filterLocalizationIds.value = [];
  filterServerIds.value = [];
  filterRecruiting.value = ALL_RECRUITING_VALUE;
  filterTagIds.value = [];
  try {
    sessionStorage.removeItem(guildsFilterStorageKey());
  } catch {
    // ignore
  }
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

  if (typeof q.is_recruiting === 'string' && q.is_recruiting.trim() !== '') {
    filterRecruiting.value = q.is_recruiting.trim() === '1' ? '1' : ALL_RECRUITING_VALUE;
  }

  if (Array.isArray(q.tag_ids)) {
    filterTagIds.value = q.tag_ids.map((id) => Number(id)).filter((id) => !Number.isNaN(id));
  } else if (typeof q.tag_ids === 'string' && q.tag_ids.trim() !== '') {
    filterTagIds.value = q.tag_ids
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
  if (filterRecruiting.value === '1') {
    query.is_recruiting = '1';
  }
  if (filterTagIds.value.length) {
    query.tag_ids = filterTagIds.value.join(',');
  }
  router.replace({ query: Object.keys(query).length ? query : {} });
}

/** Загрузить данные для фильтра (игра с локалями и серверами). */
async function loadFilterOptions() {
  loadingFilterOptions.value = true;
  try {
    if (isGameSubdomain.value && siteContext.game?.id) {
      gameDetail.value = await gamesApi.getGame(siteContext.game.id);
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
      is_recruiting?: boolean;
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
    if (filterRecruiting.value === '1') params.is_recruiting = true;

    const { guilds: list } = await guildsApi.getGuilds(params);
    allGuilds.value = list;
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить гильдии';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

const filterReady = ref(false);
/** Не сбрасывать локализации при первой установке game_id из URL или sessionStorage. */
const suppressGameIdWatch = ref(true);

onMounted(async () => {
  if (hasGuildsFilterInQuery(route.query)) {
    applyFilterFromQuery();
  } else {
    applyGuildsFilterFromSession();
  }
  await loadFilterOptions();
  await loadGuilds(true);
  loadMemberGuildIds();
  filterReady.value = true;
  suppressGameIdWatch.value = false;
  persistGuildsFilterToSession();
});

let loadGuildsTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
  [filterName, filterGameId, filterLocalizationIds, filterServerIds, filterRecruiting],
  () => {
    if (!filterReady.value) return;
    if (loadGuildsTimeout) clearTimeout(loadGuildsTimeout);
    loadGuildsTimeout = setTimeout(() => {
      syncFilterToQuery();
      persistGuildsFilterToSession();
      loadGuilds();
    }, 350);
  },
  { deep: true }
);

let tagsTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
  filterTagIds,
  () => {
    if (!filterReady.value) return;
    if (tagsTimeout) clearTimeout(tagsTimeout);
    tagsTimeout = setTimeout(() => {
      syncFilterToQuery();
      persistGuildsFilterToSession();
    }, 150);
  },
  { deep: true }
);

watch(() => siteContext.game?.id, () => {
  loadMemberGuildIds();
});

watch(filterGameId, async () => {
  if (suppressGameIdWatch.value) return;
  filterLocalizationIds.value = [];
  filterServerIds.value = [];
  const gameId =
    filterGameId.value && filterGameId.value !== ALL_GAMES_VALUE
      ? Number(filterGameId.value)
      : null;
  if (gameId) {
    try {
      gameDetail.value = await gamesApi.getGame(gameId);
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

      <ResponsiveFiltersToolbar
        v-model:name="filterName"
        class="mb-6"
        name-label="Название"
        name-placeholder="Поиск по названию..."
        :extra-filters-active="guildsExtraFiltersActive"
        :extra-filters-title="guildsExtraFiltersUiTitle"
        :popover-trigger-title="guildsExtraFiltersUiTitle"
        :popover-trigger-aria-label="guildsExtraFiltersUiAria"
        reset-button-title="Сбросить фильтр"
        reset-button-aria-label="Сбросить фильтр"
        name-mobile-input-id="guild-list-filter-name-mobile"
        name-desktop-input-id="guild-list-filter-name-desktop"
        @reset="resetFilter"
      >
        <template #extra-filters>
          <template v-if="!isGameSubdomain">
            <div class="grid gap-1.5">
              <Label for="guild-filter-game-mobile">Игра</Label>
              <Select
                id="guild-filter-game-mobile"
                v-model="filterGameId"
                :options="gameOptions"
                placeholder="Игра"
                :disabled="loadingFilterOptions || !games.length"
                trigger-class="min-h-8 w-full"
              />
            </div>
          </template>
          <div class="grid gap-1.5">
            <Label>Локализация</Label>
            <MultiSelect
              :model-value="filterLocalizationIds"
              :options="localizationMultiOptions"
              placeholder="Все"
              search-placeholder="Поиск локализации..."
              empty-text="Нет локализаций"
              :disabled="loadingFilterOptions || !filterLocalizations.length"
              trigger-class="min-h-8 w-full min-w-0"
              @update:model-value="onLocalizationsChange"
            />
          </div>
          <div class="grid gap-1.5">
            <Label>Сервер</Label>
            <MultiSelect
              :model-value="filterServerIds"
              :options="serverMultiOptions"
              placeholder="Все"
              search-placeholder="Поиск сервера..."
              empty-text="Нет серверов"
              :disabled="!filterServers.length"
              trigger-class="min-h-8 w-full min-w-0"
              @update:model-value="onServersChange"
            />
          </div>
          <div class="grid gap-1.5">
            <Label for="guild-filter-recruiting-mobile">Набор</Label>
            <Select
              id="guild-filter-recruiting-mobile"
              v-model="filterRecruiting"
              :options="recruitingOptions"
              placeholder="Любой"
              trigger-class="min-h-8 w-full"
            />
          </div>
          <div class="grid gap-1.5">
            <Label>Теги</Label>
            <MultiSelect
              :model-value="filterTagIds"
              :options="tagMultiOptions"
              placeholder="Все"
              search-placeholder="Поиск тега..."
              empty-text="Нет тегов"
              :disabled="!tagMultiOptions.length"
              trigger-class="min-h-8 w-full min-w-0"
              display-mode="badges"
              @update:model-value="onTagsChange"
            />
          </div>
        </template>
        <template #desktop-filters>
          <template v-if="!isGameSubdomain">
            <div class="grid w-36 shrink-0 gap-1.5 sm:w-40">
              <Label for="guild-filter-game">Игра</Label>
              <Select
                id="guild-filter-game"
                v-model="filterGameId"
                :options="gameOptions"
                placeholder="Игра"
                :disabled="loadingFilterOptions || !games.length"
                trigger-class="min-h-8 w-full"
              />
            </div>
          </template>
          <div class="grid w-32 shrink-0 gap-1.5 sm:w-36">
            <Label>Локализация</Label>
            <MultiSelect
              :model-value="filterLocalizationIds"
              :options="localizationMultiOptions"
              placeholder="Все"
              search-placeholder="Поиск локализации..."
              empty-text="Нет локализаций"
              :disabled="loadingFilterOptions || !filterLocalizations.length"
              trigger-class="min-h-8 w-full min-w-0"
              @update:model-value="onLocalizationsChange"
            />
          </div>
          <div class="grid w-36 shrink-0 gap-1.5 sm:w-40">
            <Label>Сервер</Label>
            <MultiSelect
              :model-value="filterServerIds"
              :options="serverMultiOptions"
              placeholder="Все"
              search-placeholder="Поиск сервера..."
              empty-text="Нет серверов"
              :disabled="!filterServers.length"
              trigger-class="min-h-8 w-full min-w-0"
              @update:model-value="onServersChange"
            />
          </div>
          <div class="grid w-28 shrink-0 gap-1.5 sm:w-32">
            <Label for="guild-filter-recruiting">Набор</Label>
            <Select
              id="guild-filter-recruiting"
              v-model="filterRecruiting"
              :options="recruitingOptions"
              placeholder="Любой"
              trigger-class="min-h-8 w-full"
            />
          </div>
          <div class="grid min-w-[9rem] flex-1 basis-0 gap-1.5 min-[480px]:min-w-[10rem]">
            <Label>Теги</Label>
            <MultiSelect
              :model-value="filterTagIds"
              :options="tagMultiOptions"
              placeholder="Все"
              search-placeholder="Поиск тега..."
              empty-text="Нет тегов"
              :disabled="!tagMultiOptions.length"
              trigger-class="min-h-8 w-full min-w-0"
              display-mode="badges"
              @update:model-value="onTagsChange"
            />
          </div>
        </template>
      </ResponsiveFiltersToolbar>

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
          v-if="loading && visibleGuilds.length === 0"
          class="pt-10"
        >
          <!-- Пустое место при первой загрузке, карточки появятся после -->
        </div>

        <div
          v-else-if="visibleGuilds.length === 0"
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
          v-for="(g, i) in (visibleGuilds as Guild[])"
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
