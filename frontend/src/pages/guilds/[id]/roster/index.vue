<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { Card, CardContent, Badge, Input, Button, Label, Select, type SelectOption, MultiSelect, type MultiSelectOption } from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import { guildsApi, type Guild, type GuildRosterMember } from '@/shared/api/guildsApi';
import {
  rosterTagBadgeClass,
  rosterTagDisplayRows,
  sliceRosterTagRowsForDisplay,
  isRosterCommonTag,
} from '@/shared/lib/rosterTagDisplay';

const route = useRoute();
const router = useRouter();

const guildId = computed(() => Number(route.params.id));

const guild = ref<Guild | null>(null);
const guildLoading = ref(true);
const guildError = ref<string | null>(null);

const roster = ref<GuildRosterMember[]>([]);
const rosterLoading = ref(false);
const rosterFetched = ref(false);
const rosterErrorStatus = ref<number | null>(null);

const filterName = ref('');
/** '' = все роли */
const filterGuildRole = ref<string>('');
const filterTagIds = ref<(string | number)[]>([]);

const exportRosterLoading = ref(false);
const exportRosterError = ref('');

const guildRoleOptions = computed<SelectOption[]>(() => {
  const roles = new Map<string, string>(); // slug -> name
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
  filterTagIds.value = [];
}

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

const rosterNeedsLogin = computed(
  () =>
    rosterFetched.value && !rosterLoading.value && rosterErrorStatus.value === 401
);

const accessDenied = computed(
  () =>
    rosterFetched.value && !rosterLoading.value && rosterErrorStatus.value === 403
);

async function loadRoster() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  rosterLoading.value = true;
  rosterErrorStatus.value = null;
  roster.value = [];
  try {
    roster.value = await guildsApi.getGuildRoster(guildId.value);
  } catch (e: unknown) {
    const err = e as { status?: number };
    rosterErrorStatus.value = err.status ?? -1;
  } finally {
    rosterLoading.value = false;
    rosterFetched.value = true;
  }
}

async function loadGuild() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  guildLoading.value = true;
  guildError.value = null;
  try {
    guild.value = await guildsApi.getGuild(guildId.value);
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

watch(guildId, async () => {
  rosterFetched.value = false;
  roster.value = [];
  rosterErrorStatus.value = null;
  await loadGuild();
  if (guild.value) {
    void loadRoster();
  }
}, { immediate: true });
</script>

<template>
  <div class="container py-4 md:py-8">
    <div class="mx-auto max-w-4xl">
      <div v-if="guildError" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ guildError }}
      </div>

      <div v-if="guildLoading" class="text-muted-foreground">Загрузка…</div>

      <template v-else-if="guild">
        <h1 class="mb-6 text-2xl font-bold md:text-3xl">
          Состав: {{ guild.name }}
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
        <p v-else-if="accessDenied" class="text-sm text-muted-foreground">
          Состав гильдии доступен только её участникам или при открытом показе состава в настройках гильдии.
        </p>
        <p
          v-else-if="rosterFetched && rosterErrorStatus != null && rosterErrorStatus !== 401 && rosterErrorStatus !== 403"
          class="text-sm text-destructive"
        >
          Не удалось загрузить состав. Попробуйте обновить страницу.
        </p>
        <template v-else-if="!rosterNeedsLogin && !accessDenied && rosterFetched && !rosterLoading && rosterErrorStatus === null">
          <p v-if="roster.length === 0" class="text-sm text-muted-foreground">
            В гильдии пока никого нет.
          </p>
          <template v-else>
            <Card class="mb-5">
              <CardContent class="p-4">
                <div class="flex flex-col gap-3">
                  <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end">
                    <div class="grid gap-1.5 md:w-[220px]">
                      <Label for="roster-filter-name">Имя</Label>
                      <Input
                        id="roster-filter-name"
                        v-model="filterName"
                        type="text"
                        placeholder="Например: Alex"
                        class="h-8"
                      />
                    </div>
                    <div class="grid gap-1.5 md:w-[220px]">
                      <Label>Роль в гильдии</Label>
                      <Select
                        v-model="filterGuildRole"
                        :options="guildRoleOptions"
                        placeholder="Все роли"
                        trigger-class="h-8 w-full"
                      />
                    </div>
                    <div class="grid gap-1.5 md:min-w-[260px] md:flex-1">
                      <Label>Теги</Label>
                      <MultiSelect
                        v-model="filterTagIds"
                        :options="tagOptions"
                        placeholder="Любые (общие и гильдии)"
                        search-placeholder="Поиск тегов..."
                        trigger-class="h-8 w-full"
                        display-mode="badges"
                      />
                    </div>
                    <div class="flex gap-2 md:ml-auto">
                      <Button
                        type="button"
                        variant="secondary"
                        class="h-8 w-full md:w-8 md:px-0"
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
                      <Button
                        type="button"
                        variant="secondary"
                        class="h-8 w-full md:w-8 md:px-0"
                        title="Сбросить фильтры"
                        aria-label="Сбросить фильтры"
                        @click="resetFilters"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="16"
                          height="16"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          aria-hidden="true"
                        >
                          <path d="M3 6h18" />
                          <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                          <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                          <path d="M10 11v6" />
                          <path d="M14 11v6" />
                        </svg>
                      </Button>
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-muted-foreground">
                      Показано: {{ rosterDisplayItems.length }} из {{ roster.length }}
                    </p>
                  </div>
                  <p v-if="exportRosterError" class="text-xs text-destructive">
                    {{ exportRosterError }}
                  </p>
                </div>
              </CardContent>
            </Card>

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
                  <div class="flex w-full items-center gap-3">
                    <Avatar
                      :src="member.avatar_url ?? undefined"
                      :alt="member.name"
                      :fallback="avatarFallback(member.name)"
                      class="h-12 w-12 shrink-0 md:h-14 md:w-14"
                    />
                    <div class="min-w-0 flex-1">
                      <p class="truncate font-medium">{{ member.name }}</p>
                      <Badge
                        v-if="member.guild_role"
                        variant="secondary"
                        class="mt-1 text-xs"
                      >
                        {{ member.guild_role.name }}
                      </Badge>
                    </div>
                  </div>
                  <div v-if="member.game_classes.length > 0" class="flex flex-wrap gap-1">
                    <Badge
                      v-for="gc in member.game_classes"
                      :key="gc.id"
                      variant="outline"
                      class="text-xs"
                    >
                      {{ gc.name_ru ?? gc.name }}
                    </Badge>
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
