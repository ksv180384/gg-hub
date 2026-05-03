<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { Card, CardContent, Badge, Button, Label, Select, type SelectOption, MultiSelect, type MultiSelectOption } from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import { ResponsiveFiltersToolbar } from '@/widgets/responsive-filters-toolbar';
import CharacterClassBadge from '@/pages/characters/CharacterClassBadge.vue';
import { guildsApi, type Guild, type GuildRosterMember, type GuildRosterRoleSummary } from '@/shared/api/guildsApi';
import { gamesApi, type GameClass, type GameClassCatalogItem } from '@/shared/api/gamesApi';
import {
  rosterTagBadgeClass,
  rosterTagDisplayRows,
  sliceRosterTagRowsForDisplay,
  isRosterCommonTag,
} from '@/shared/lib/rosterTagDisplay';
import NotFoundPage from '@/pages/not-found/index.vue';

const route = useRoute();
const router = useRouter();

const guildId = computed(() => Number(route.params.id));

const guild = ref<Guild | null>(null);
const guildLoading = ref(true);
const guildError = ref<string | null>(null);

const roster = ref<GuildRosterMember[]>([]);
/** Роли гильдии с API состава (meta.guild_roles), не только у текущих участников. */
const guildRosterRoles = ref<GuildRosterRoleSummary[]>([]);
const rosterLoading = ref(false);
const rosterFetched = ref(false);
const rosterErrorStatus = ref<number | null>(null);
/** Нет доступа к составу (403/404 API) — показываем UI «не найдено», URL не меняем. */
const rosterForbiddenRedirect = ref(false);

const filterName = ref('');
/** '' = все роли */
const filterGuildRole = ref<string>('');
const filterGameClassIds = ref<(string | number)[]>([]);
const filterTagIds = ref<(string | number)[]>([]);

/** Справочник классов игры (GET /games/:id/game-classes). */
const gameClassesCatalog = ref<GameClassCatalogItem[]>([]);
/** Лимит выбора классов в фильтре = «Классов у персонажа» в настройках игры. */
const gameMaxClassesPerCharacter = ref(1);

const exportRosterLoading = ref(false);
const exportRosterError = ref('');

const rosterExtraFiltersActive = computed(
  () =>
    !!filterGuildRole.value ||
    filterGameClassIds.value.length > 0 ||
    filterTagIds.value.length > 0,
);

const guildRoleOptions = computed<SelectOption[]>(() => {
  const fromMeta = guildRosterRoles.value;
  if (fromMeta.length > 0) {
    return [...fromMeta]
      .filter((r) => r.slug)
      .sort((a, b) => a.name.localeCompare(b.name, 'ru'))
      .map((r) => ({ value: r.slug, label: r.name }));
  }
  const roles = new Map<string, string>();
  roster.value.forEach((m) => {
    const r = m.guild_role;
    if (!r?.slug) return;
    if (!roles.has(r.slug)) roles.set(r.slug, r.name);
  });
  return Array.from(roles.entries())
    .sort((a, b) => a[1].localeCompare(b[1], 'ru'))
    .map(([slug, name]) => ({ value: slug, label: name }));
});

watch(guildRoleOptions, (opts) => {
  if (!filterGuildRole.value) return;
  if (!opts.some((o) => o.value === filterGuildRole.value)) {
    filterGuildRole.value = '';
  }
});

const gameClassOptions = computed<MultiSelectOption[]>(() => {
  const catalog = gameClassesCatalog.value;
  if (catalog.length > 0) {
    return [...catalog]
      .sort((a, b) => (a.name_ru ?? a.name).localeCompare(b.name_ru ?? b.name, 'ru'))
      .map((gc) => ({
        value: gc.id,
        label: (gc.name_ru ?? gc.name).trim() || String(gc.id),
        imageUrl: gc.image_thumb,
      }));
  }
  const map = new Map<number, { label: string; imageUrl?: string | null }>();
  roster.value.forEach((m) => {
    for (const gc of m.game_classes ?? []) {
      if (!map.has(gc.id)) {
        map.set(gc.id, {
          label: (gc.name_ru ?? gc.name).trim() || String(gc.id),
          imageUrl: gc.image_thumb ?? undefined,
        });
      }
    }
  });
  return Array.from(map.entries())
    .sort((a, b) => a[1].label.localeCompare(b[1].label, 'ru'))
    .map(([id, meta]) => ({
      value: id,
      label: meta.label,
      imageUrl: meta.imageUrl ?? null,
    }));
});

watch(gameClassOptions, (opts) => {
  const valid = new Set(opts.map((o) => o.value));
  filterGameClassIds.value = filterGameClassIds.value.filter((v) => valid.has(v));
});

async function exportRosterXlsx() {
  if (exportRosterLoading.value) return;
  const g = guild.value;
  if (!g) return;
  if (filteredRoster.value.length === 0) return;
  exportRosterLoading.value = true;
  exportRosterError.value = '';
  try {
    const { exportGuildRosterToXlsx } = await import('@/shared/lib/guildRosterXlsx');
    await exportGuildRosterToXlsx({
      guildName: g.name,
      members: filteredRoster.value,
    });
  } catch (e: unknown) {
    exportRosterError.value = e instanceof Error ? e.message : 'Не удалось выгрузить состав.';
  } finally {
    exportRosterLoading.value = false;
  }
}

const tagOptions = computed<MultiSelectOption[]>(() => {
  const map = new Map<number, { label: string; badgeClass?: string; order: 0 | 1 }>();
  const tagTextClass = (source: 'guild' | 'personal', tag: { used_by_user_id?: number | null; used_by_guild_id?: number | null }) => {
    if (source === 'guild') return 'text-violet-700 dark:text-violet-300';
    if (isRosterCommonTag(tag)) return 'text-blue-700 dark:text-blue-300';
    return '';
  };
  roster.value.forEach((m) => {
    for (const row of rosterTagDisplayRows(m)) {
      // В селекте нужны только: теги гильдии (фиолетовые) и общие теги (синие).
      if (row.source === 'guild') {
        if (!map.has(row.tag.id)) {
          map.set(row.tag.id, {
            label: row.tag.name,
            badgeClass: tagTextClass('guild', row.tag),
            order: 1,
          });
        }
        continue;
      }
      if (isRosterCommonTag(row.tag)) {
        if (!map.has(row.tag.id)) {
          map.set(row.tag.id, {
            label: row.tag.name,
            badgeClass: tagTextClass('personal', row.tag),
            order: 0,
          });
        }
      }
    }
  });
  return Array.from(map.entries())
    .sort((a, b) => {
      if (a[1].order !== b[1].order) return a[1].order - b[1].order; // общие выше гильдийских
      return a[1].label.localeCompare(b[1].label, 'ru');
    })
    .map(([id, meta]) => ({ value: id, label: meta.label, badgeClass: meta.badgeClass }));
});

function applyNameFilter(items: GuildRosterMember[], value: string): GuildRosterMember[] {
  const q = value.trim().toLowerCase();
  if (!q) return items;
  return items.filter((m) => m.name.toLowerCase().includes(q));
}

function applyGuildRoleFilter(items: GuildRosterMember[], roleSlug: string): GuildRosterMember[] {
  const slug = roleSlug.trim();
  if (!slug) return items;
  return items.filter((m) => (m.guild_role?.slug ?? '') === slug);
}

/** Персонаж должен иметь все выбранные классы (пересечение по полному набору фильтра). */
function applyGameClassAllFilter(
  items: GuildRosterMember[],
  selected: (string | number)[],
): GuildRosterMember[] {
  const selectedIds = selected
    .map((v) => Number(v))
    .filter((n) => Number.isInteger(n) && n > 0);
  if (selectedIds.length === 0) return items;
  return items.filter((m) => {
    const memberIds = new Set((m.game_classes ?? []).map((gc) => gc.id));
    return selectedIds.every((id) => memberIds.has(id));
  });
}

function applyAllTagIdsFilter(
  items: GuildRosterMember[],
  selected: (string | number)[],
  getMemberTagIds: (m: GuildRosterMember) => number[]
): GuildRosterMember[] {
  const selectedIds = selected
    .map((v) => Number(v))
    .filter((n) => Number.isInteger(n) && n > 0);
  if (selectedIds.length === 0) return items;
  return items.filter((m) => {
    const memberIds = new Set(getMemberTagIds(m));
    return selectedIds.every((id) => memberIds.has(id));
  });
}

const filteredRoster = computed<GuildRosterMember[]>(() => {
  let items = roster.value;
  items = applyNameFilter(items, filterName.value);
  items = applyGuildRoleFilter(items, filterGuildRole.value);
  items = applyGameClassAllFilter(items, filterGameClassIds.value);
  items = applyAllTagIdsFilter(items, filterTagIds.value, (m) =>
    [...(m.tags ?? []), ...(m.personal_tags ?? [])].map((t) => t.id)
  );
  return items;
});

const rosterDisplayItems = computed(() =>
  filteredRoster.value.map((member) => ({
    member,
    tagsUi: sliceRosterTagRowsForDisplay(rosterTagDisplayRows(member)),
  }))
);

function resetFilters() {
  filterName.value = '';
  filterGuildRole.value = '';
  filterGameClassIds.value = [];
  filterTagIds.value = [];
}

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

function rosterMemberGameClass(gc: GuildRosterMember['game_classes'][number]): GameClass {
  return {
    id: gc.id,
    game_id: 0,
    name: gc.name,
    name_ru: gc.name_ru ?? null,
    slug: gc.slug,
    image: gc.image ?? null,
    image_thumb: gc.image_thumb ?? null,
  };
}

const rosterNeedsLogin = computed(
  () =>
    rosterFetched.value && !rosterLoading.value && rosterErrorStatus.value === 401
);

/** Счётчик N из M в заголовке; в подсказке — полная фраза «Показано: …». */
const rosterHeadingCount = computed(() => {
  if (
    !rosterFetched.value ||
    rosterLoading.value ||
    rosterErrorStatus.value !== null ||
    rosterNeedsLogin.value ||
    roster.value.length === 0
  ) {
    return null;
  }
  const shown = rosterDisplayItems.value.length;
  const total = roster.value.length;
  return {
    shown,
    total,
    title: `Показано: ${shown} из ${total}`,
  };
});

async function loadRoster() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  rosterLoading.value = true;
  rosterErrorStatus.value = null;
  roster.value = [];
  guildRosterRoles.value = [];
  try {
    const { members, guild_roles } = await guildsApi.getGuildRoster(guildId.value);
    roster.value = members;
    guildRosterRoles.value = guild_roles;
  } catch (e: unknown) {
    const err = e as { status?: number };
    const st = err.status ?? -1;
    rosterErrorStatus.value = st;
    if (st === 403 || st === 404) {
      rosterForbiddenRedirect.value = true;
    }
  } finally {
    rosterLoading.value = false;
    rosterFetched.value = true;
  }
}

async function loadGameClassesCatalog() {
  const g = guild.value;
  if (!g?.game_id) {
    gameClassesCatalog.value = [];
    return;
  }
  try {
    gameClassesCatalog.value = await gamesApi.getGameClasses(g.game_id);
  } catch {
    gameClassesCatalog.value = [];
  }
}

async function loadGameMaxClassesPerCharacter() {
  const g = guild.value;
  if (!g?.game_id) {
    gameMaxClassesPerCharacter.value = 1;
    return;
  }
  try {
    const game = await gamesApi.getGame(g.game_id);
    gameMaxClassesPerCharacter.value = Math.max(1, game.max_classes_per_character ?? 1);
  } catch {
    gameMaxClassesPerCharacter.value = 1;
  }
}

async function loadGuild() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  guildLoading.value = true;
  guildError.value = null;
  try {
    guild.value = await guildsApi.getGuild(guildId.value);
    void loadGameClassesCatalog();
    void loadGameMaxClassesPerCharacter();
  } catch (e: unknown) {
    const err = e as { status?: number };
    if (err.status === 404) {
      router.replace('/guilds');
      return;
    }
    guildError.value = 'Не удалось загрузить гильдию';
  } finally {
    guildLoading.value = false;
  }
}

watch(gameMaxClassesPerCharacter, (max) => {
  const cap = Math.max(1, max);
  if (filterGameClassIds.value.length > cap) {
    filterGameClassIds.value = filterGameClassIds.value.slice(0, cap);
  }
});

watch(guildId, async () => {
  gameClassesCatalog.value = [];
  gameMaxClassesPerCharacter.value = 1;
  rosterFetched.value = false;
  roster.value = [];
  guildRosterRoles.value = [];
  rosterErrorStatus.value = null;
  rosterForbiddenRedirect.value = false;
  await loadGuild();
  if (guild.value) {
    void loadRoster();
  }
}, { immediate: true });
</script>

<template>
  <NotFoundPage v-if="rosterForbiddenRedirect" />
  <div v-else class="container py-4 md:py-8">
    <div class="mx-auto max-w-4xl">
      <div v-if="guildError" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ guildError }}
      </div>

      <div v-if="guildLoading" class="text-muted-foreground">Загрузка…</div>

      <template v-else-if="guild">
        <h1
          class="mb-6 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-2xl font-bold md:text-3xl"
        >
          <span>Состав: {{ guild.name }}</span>
          <span
            v-if="rosterHeadingCount"
            :title="rosterHeadingCount.title"
            class="text-xs font-normal text-muted-foreground"
          >
            {{ rosterHeadingCount.shown }} из {{ rosterHeadingCount.total }}
          </span>
        </h1>

        <p v-if="rosterLoading" class="text-sm text-muted-foreground">
          Загрузка состава…
        </p>
        <p v-else-if="rosterNeedsLogin" class="text-sm text-muted-foreground">
          <RouterLink
            :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="font-medium text-primary underline-offset-4 hover:underline"
          >
            Войдите в аккаунт
          </RouterLink>
          , чтобы увидеть список участников.
        </p>
        <p
          v-else-if="
            rosterFetched &&
              rosterErrorStatus != null &&
              rosterErrorStatus !== 401 &&
              rosterErrorStatus !== 403 &&
              rosterErrorStatus !== 404
          "
          class="text-sm text-destructive"
        >
          Не удалось загрузить состав. Попробуйте обновить страницу.
        </p>
        <template v-else-if="!rosterNeedsLogin && rosterFetched && !rosterLoading && rosterErrorStatus === null">
          <p v-if="roster.length === 0" class="text-sm text-muted-foreground">
            В гильдии пока никого нет.
          </p>
          <template v-else>
            <ResponsiveFiltersToolbar
              v-model:name="filterName"
              class="mb-5"
              :extra-filters-active="rosterExtraFiltersActive"
              name-placeholder="Например: Alex"
              popover-trigger-title="Роль, классы, теги"
              popover-trigger-aria-label="Открыть фильтры: роль, классы, теги"
              name-mobile-input-id="roster-filter-name-mobile"
              name-desktop-input-id="roster-filter-name"
              @reset="resetFilters"
            >
              <template #extra-filters>
                <div class="grid gap-1.5">
                  <Label>Роль в гильдии</Label>
                  <Select
                    v-model="filterGuildRole"
                    :options="guildRoleOptions"
                    placeholder="Все роли"
                    trigger-class="min-h-8 w-full"
                  />
                </div>
                <div class="grid gap-1.5">
                  <Label>Классы</Label>
                  <MultiSelect
                    v-model="filterGameClassIds"
                    :options="gameClassOptions"
                    :max-selected="gameMaxClassesPerCharacter"
                    hide-actions
                    placeholder="Все классы"
                    search-placeholder="Поиск классов..."
                    trigger-class="min-h-8 w-full min-w-0"
                    display-mode="badges"
                  />
                </div>
                <div class="grid gap-1.5">
                  <Label>Теги</Label>
                  <MultiSelect
                    v-model="filterTagIds"
                    :options="tagOptions"
                    placeholder="Любые (общие и гильдии)"
                    search-placeholder="Поиск тегов..."
                    trigger-class="min-h-8 w-full min-w-0"
                    display-mode="badges"
                  />
                </div>
              </template>
              <template #desktop-filters>
                <div class="grid w-36 shrink-0 gap-1.5 sm:w-40">
                  <Label>Роль в гильдии</Label>
                  <Select
                    v-model="filterGuildRole"
                    :options="guildRoleOptions"
                    placeholder="Все роли"
                    trigger-class="min-h-8 w-full"
                  />
                </div>
                <div class="grid min-w-[9rem] flex-1 basis-0 gap-1.5 min-[480px]:min-w-[10rem]">
                  <Label>Классы</Label>
                  <MultiSelect
                    v-model="filterGameClassIds"
                    :options="gameClassOptions"
                    :max-selected="gameMaxClassesPerCharacter"
                    hide-actions
                    placeholder="Все классы"
                    search-placeholder="Поиск классов..."
                    trigger-class="min-h-8 w-full min-w-0"
                    display-mode="badges"
                  />
                </div>
                <div class="grid min-w-[9rem] flex-1 basis-0 gap-1.5 min-[480px]:min-w-[10rem]">
                  <Label>Теги</Label>
                  <MultiSelect
                    v-model="filterTagIds"
                    :options="tagOptions"
                    placeholder="Любые (общие и гильдии)"
                    search-placeholder="Поиск тегов..."
                    trigger-class="min-h-8 w-full min-w-0"
                    display-mode="badges"
                  />
                </div>
              </template>
              <template #after-reset-actions>
                <Button
                  type="button"
                  variant="secondary"
                  class="h-8 w-8 cursor-pointer px-0 disabled:cursor-not-allowed"
                  title="Выгрузить в Excel"
                  aria-label="Выгрузить в Excel"
                  :disabled="exportRosterLoading || rosterDisplayItems.length === 0"
                  @click="exportRosterXlsx"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                    width="16"
                    height="16"
                  >
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" x2="12" y1="15" y2="3" />
                  </svg>
                </Button>
              </template>
              <template #after-reset-actions-desktop>
                <Button
                  type="button"
                  variant="secondary"
                  class="h-8 w-8 cursor-pointer px-0 disabled:cursor-not-allowed"
                  title="Выгрузить в Excel"
                  aria-label="Выгрузить в Excel"
                  :disabled="exportRosterLoading || rosterDisplayItems.length === 0"
                  @click="exportRosterXlsx"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                    width="16"
                    height="16"
                  >
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" x2="12" y1="15" y2="3" />
                  </svg>
                </Button>
              </template>
              <template #footer>
                <p v-if="exportRosterError" class="text-xs text-destructive">
                  {{ exportRosterError }}
                </p>
              </template>
            </ResponsiveFiltersToolbar>

            <p v-if="rosterDisplayItems.length === 0" class="text-sm text-muted-foreground">
              Ничего не найдено по заданным фильтрам.
            </p>

            <div
              v-else
              class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
            <RouterLink
              v-for="{ member, tagsUi } in rosterDisplayItems"
              :key="member.character_id"
              :to="{ name: 'guild-roster-member', params: { id: String(guildId), characterId: String(member.character_id) } }"
              class="block transition-opacity hover:opacity-90 focus-visible:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
              <Card class="h-full overflow-hidden">
                <CardContent class="flex flex-col items-start gap-3 p-4">
                  <div class="flex w-full items-start gap-3">
                    <Avatar
                      :src="member.avatar_url ?? undefined"
                      :alt="member.name"
                      :fallback="avatarFallback(member.name)"
                      class="h-12 w-12 shrink-0 md:h-14 md:w-14"
                    />
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                      <div class="flex min-w-0 items-center gap-2">
                        <p class="min-w-0 truncate text-lg font-medium">{{ member.name }}</p>
                        <Badge
                          v-if="member.guild_role"
                          variant="secondary"
                          class="shrink-0 text-xs"
                        >
                          {{ member.guild_role.name }}
                        </Badge>
                      </div>
                      <div
                        v-if="member.game_classes.length > 0"
                        class="flex flex-wrap items-center gap-2"
                      >
                        <CharacterClassBadge
                          v-for="gc in member.game_classes"
                          :key="gc.id"
                          :game-class="rosterMemberGameClass(gc)"
                        />
                      </div>
                    </div>
                  </div>
                  <div class="flex flex-wrap items-center gap-1">
                    <Badge
                      v-for="row in tagsUi.visible"
                      :key="row.source + '-' + row.tag.id"
                      variant="outline"
                      :class="[rosterTagBadgeClass(row.source, row.tag), 'text-xs']"
                    >
                      {{ row.tag.name }}
                    </Badge>
                    <span
                      v-if="tagsUi.moreCount > 0"
                      class="text-xs text-muted-foreground"
                      :title="`Ещё ${tagsUi.moreCount} тегов`"
                    >
                      +{{ tagsUi.moreCount }}
                    </span>
                  </div>
                </CardContent>
              </Card>
            </RouterLink>
            </div>
          </template>
        </template>
      </template>
    </div>
  </div>
</template>
