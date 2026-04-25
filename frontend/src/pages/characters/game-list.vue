<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Input,
  Label,
  MultiSelect,
  Spinner,
} from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { gamesApi, type Game, type Localization, type Server } from '@/shared/api/gamesApi';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import CharacterClassBadge from './CharacterClassBadge.vue';

const router = useRouter();
const route = useRoute();
const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const characters = ref<Character[]>([]);
const allTags = ref<Tag[]>([]);
const loading = ref(false);
const refreshing = ref(false);
const error = ref<string | null>(null);

// Фильтр
const filterName = ref('');
const filterLocalizationIds = ref<number[]>([]);
const filterServerIds = ref<number[]>([]);
const filterGameClassIds = ref<number[]>([]);
const filterCommonTagIds = ref<number[]>([]);

// Данные для фильтра
const gameDetail = ref<Game | null>(null);
const loadingFilterOptions = ref(false);

/** Локализации для фильтра. */
const filterLocalizations = computed<Localization[]>(() => {
  const list = gameDetail.value?.localizations?.filter((l) => l.is_active !== false) ?? [];
  return list;
});

/** Серверы для выбранных локализаций. */
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

/** Классы игры для фильтра. */
const filterGameClasses = computed(() => gameDetail.value?.game_classes ?? []);

const localizationMultiOptions = computed(() =>
  filterLocalizations.value.map((l) => ({ value: l.id, label: l.name }))
);
const serverMultiOptions = computed(() =>
  filterServers.value.map((s) => ({ value: s.id, label: s.name }))
);
const gameClassMultiOptions = computed(() =>
  filterGameClasses.value.map((gc) => ({
    value: gc.id,
    label: gc.name_ru ?? gc.name,
  }))
);

const commonTags = computed((): Tag[] =>
  (allTags.value ?? [])
    .filter((t) => !t.is_hidden)
    .filter((t) => (t.used_by_user_id == null) && (t.used_by_guild_id == null))
    .sort((a, b) => a.name.localeCompare(b.name))
);

const commonTagMultiOptions = computed(() =>
  commonTags.value.map((t) => ({ value: t.id, label: t.name }))
);

function onLocalizationsChange(value: (string | number)[]) {
  filterLocalizationIds.value = value.map(Number);
  const serverIdSet = new Set(filterServers.value.map((s) => s.id));
  filterServerIds.value = filterServerIds.value.filter((id) => serverIdSet.has(id));
}
function onServersChange(value: (string | number)[]) {
  filterServerIds.value = value.map(Number);
}

function onGameClassesChange(value: (string | number)[]) {
  filterGameClassIds.value = value.map(Number);
}

function onCommonTagsChange(value: (string | number)[]) {
  filterCommonTagIds.value = value.map(Number);
}

function resetFilter() {
  filterName.value = '';
  filterLocalizationIds.value = [];
  filterServerIds.value = [];
  filterGameClassIds.value = [];
  filterCommonTagIds.value = [];
}

function applyFilterFromQuery() {
  const q = route.query;
  if (typeof q.name === 'string') filterName.value = q.name;
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
  if (Array.isArray(q.game_class_ids)) {
    filterGameClassIds.value = q.game_class_ids.map((id) => Number(id)).filter((id) => !Number.isNaN(id));
  } else if (typeof q.game_class_ids === 'string' && q.game_class_ids.trim() !== '') {
    filterGameClassIds.value = q.game_class_ids
      .split(',')
      .map((id) => Number(id.trim()))
      .filter((id) => !Number.isNaN(id));
  }

  if (Array.isArray(q.common_tag_ids)) {
    filterCommonTagIds.value = q.common_tag_ids.map((id) => Number(id)).filter((id) => !Number.isNaN(id));
  } else if (typeof q.common_tag_ids === 'string' && q.common_tag_ids.trim() !== '') {
    filterCommonTagIds.value = q.common_tag_ids
      .split(',')
      .map((id) => Number(id.trim()))
      .filter((id) => !Number.isNaN(id));
  }
}

function syncFilterToQuery() {
  const query: Record<string, string> = {};
  if (filterName.value.trim()) query.name = filterName.value.trim();
  if (filterLocalizationIds.value.length) {
    query.localization_ids = filterLocalizationIds.value.join(',');
  }
  if (filterServerIds.value.length) {
    query.server_ids = filterServerIds.value.join(',');
  }
  if (filterGameClassIds.value.length) {
    query.game_class_ids = filterGameClassIds.value.join(',');
  }
  if (filterCommonTagIds.value.length) {
    query.common_tag_ids = filterCommonTagIds.value.join(',');
  }
  router.replace({ query: Object.keys(query).length ? query : {} });
}

async function loadFilterOptions() {
  if (!game.value?.id) return;
  loadingFilterOptions.value = true;
  try {
    gameDetail.value = await gamesApi.getGame(game.value.id);
    const locIds = new Set(filterLocalizations.value.map((l) => l.id));
    filterLocalizationIds.value = filterLocalizationIds.value.filter((id) => locIds.has(id));
    const serverIds = new Set(
      filterLocalizations.value.flatMap((l) => (l.servers ?? []).map((s) => s.id))
    );
    filterServerIds.value = filterServerIds.value.filter((id) => serverIds.has(id));
    const classIds = new Set(filterGameClasses.value.map((c) => c.id));
    filterGameClassIds.value = filterGameClassIds.value.filter((id) => classIds.has(id));
  } catch {
    gameDetail.value = null;
  } finally {
    loadingFilterOptions.value = false;
  }
}

async function loadTags() {
  try {
    allTags.value = await tagsApi.getTags(false);
    const allowedCommonIds = new Set(commonTags.value.map((t) => t.id));
    filterCommonTagIds.value = filterCommonTagIds.value.filter((id) => allowedCommonIds.has(id));
  } catch {
    allTags.value = [];
    filterCommonTagIds.value = [];
  }
}

async function loadCharacters(showFullLoading = false) {
  if (!game.value?.id) return;
  if (showFullLoading) {
    loading.value = true;
  } else {
    refreshing.value = true;
  }
  error.value = null;
  try {
    const params: {
      name?: string;
      localization_ids?: number[];
      server_ids?: number[];
      game_class_ids?: number[];
    } = {};
    if (filterName.value.trim()) params.name = filterName.value.trim();
    if (filterLocalizationIds.value.length) params.localization_ids = [...filterLocalizationIds.value];
    if (filterServerIds.value.length) params.server_ids = [...filterServerIds.value];
    if (filterGameClassIds.value.length) params.game_class_ids = [...filterGameClassIds.value];

    characters.value = await charactersApi.getGameCharacters(game.value.id, params);
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить персонажей';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

const visibleCharacters = computed(() => {
  if (!filterCommonTagIds.value.length) return characters.value;
  const required = new Set(filterCommonTagIds.value);
  return characters.value.filter((c) => {
    const ids = new Set((c.tags ?? []).map((t) => t.id));
    for (const id of required) {
      if (!ids.has(id)) return false;
    }
    return true;
  });
});

const filterReady = ref(false);

onMounted(async () => {
  applyFilterFromQuery();
  await loadFilterOptions();
  await loadTags();
  await loadCharacters(true);
  filterReady.value = true;
});

let loadCharactersTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
  [filterName, filterLocalizationIds, filterServerIds, filterGameClassIds, filterCommonTagIds],
  () => {
    if (!filterReady.value) return;
    if (loadCharactersTimeout) clearTimeout(loadCharactersTimeout);
    loadCharactersTimeout = setTimeout(() => {
      syncFilterToQuery();
      loadCharacters();
    }, 350);
  },
  { deep: true }
);
</script>

<template>
  <div class="container py-6">
    <Card v-if="!game" class="border-destructive/50">
      <CardHeader>
        <CardTitle>Персонажи</CardTitle>
      </CardHeader>
      <CardContent>
        <p class="text-sm text-muted-foreground">
          Перейдите на страницу игры (поддомен игры), чтобы увидеть список персонажей.
        </p>
      </CardContent>
    </Card>

    <template v-else>
      <div class="mb-4">
        <h1 class="text-xl font-semibold sm:text-2xl">Персонажи</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Все персонажи игры {{ game.name }}
        </p>
      </div>

      <!-- Фильтр -->
      <div class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border bg-muted/30 px-4 py-3">
        <div class="flex min-w-0 flex-1 flex-col gap-1 sm:min-w-[180px]">
          <Label for="character-filter-name" class="text-xs text-muted-foreground">Имя</Label>
          <Input
            id="character-filter-name"
            v-model="filterName"
            placeholder="Поиск по имени..."
            class="h-8 text-sm"
          />
        </div>
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
        <div class="flex flex-col gap-1">
          <Label class="text-xs text-muted-foreground">Класс(ы)</Label>
          <MultiSelect
            :model-value="filterGameClassIds"
            :options="gameClassMultiOptions"
            placeholder="Все"
            search-placeholder="Поиск класса..."
            empty-text="Нет классов"
            :disabled="loadingFilterOptions || !filterGameClasses.length"
            trigger-class="min-w-[140px]"
            @update:model-value="onGameClassesChange"
          />
        </div>
        <div class="flex flex-col gap-1">
          <Label class="text-xs text-muted-foreground">Общие теги</Label>
          <MultiSelect
            :model-value="filterCommonTagIds"
            :options="commonTagMultiOptions"
            placeholder="Все"
            search-placeholder="Поиск тега..."
            empty-text="Нет общих тегов"
            :disabled="!commonTags.length"
            trigger-class="min-w-[160px]"
            @update:model-value="onCommonTagsChange"
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

      <div class="relative min-h-[200px]">
        <div
          v-if="loading || refreshing"
          class="absolute left-1/2 top-2 z-10 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-muted/70 px-2 py-0.5"
          aria-busy="true"
          aria-live="polite"
        >
          <Spinner class="h-1.5 w-1.5 shrink-0 text-muted-foreground" />
          <span class="text-xs text-muted-foreground">Загрузка…</span>
        </div>

        <p v-if="loading && characters.length === 0" class="text-sm text-muted-foreground">Загрузка…</p>
        <p v-else-if="visibleCharacters.length === 0" class="text-sm text-muted-foreground">
          По выбранным критериям персонажей не найдено.
        </p>
        <ul v-else class="space-y-3">
          <li
            v-for="c in visibleCharacters"
            :key="c.id"
            class="flex flex-wrap items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50 sm:gap-4 cursor-pointer"
            @click="router.push({ name: 'game-character-show', params: { id: c.id } })"
          >
            <Avatar
              :src="c.avatar_url ?? undefined"
              :alt="c.name"
              :fallback="c.name.slice(0, 2).toUpperCase()"
              class="h-12 w-12 shrink-0"
            />
            <div class="min-w-0 flex-1">
              <p class="font-medium">{{ c.name }}</p>
              <p class="text-sm text-muted-foreground">
                <span v-if="c.localization?.name">{{ c.localization.name }}</span>
                <template v-if="c.localization?.name && c.server?.name"> · </template>
                <span v-if="c.server?.name">{{ c.server.name }}</span>
                <template v-if="!c.localization?.name && !c.server?.name">—</template>
              </p>
              <div v-if="c.game_classes?.length" class="mt-1 flex flex-wrap items-center gap-1.5">
                <CharacterClassBadge
                  v-for="gc in c.game_classes"
                  :key="gc.id"
                  :game-class="gc"
                />
              </div>
              <div v-if="c.tags?.length" class="mt-1 flex flex-wrap items-center gap-1">
                <Badge
                  v-for="tag in c.tags"
                  :key="tag.id"
                  variant="outline"
                  class="text-xs font-normal"
                >
                  {{ tag.name }}
                </Badge>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
