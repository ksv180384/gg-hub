<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Select, TagAddCombobox } from '@/shared/ui';
import type { SelectOption } from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import Badge from '@/shared/ui/badge/Badge.vue';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { guildsApi, type Guild, type GuildRosterMember, type GuildRole } from '@/shared/api/guildsApi';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';
import {
  rosterTagBadgeClass,
  sliceRosterTagRowsForDisplay,
  sortRosterTagRows,
  type RosterTagItem,
  type RosterTagRow,
} from '@/shared/lib/rosterTagDisplay';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const characterId = computed(() => Number(route.params.characterId));

const guild = ref<Guild | null>(null);
const member = ref<GuildRosterMember | null>(null);
const canExclude = ref(false);
const canChangeRole = ref(false);
const canEditGuildTags = ref(false);
const canCreateGuildTag = ref(false);
const canDeleteGuildTag = ref(false);
const guildRoles = ref<GuildRole[]>([]);
const selectedRoleId = ref<string>('');
const changingRole = ref(false);
const roleError = ref<string | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const excludeDialogOpen = ref(false);
const excluding = ref(false);
const excludeError = ref<string | null>(null);

const allTags = ref<Tag[]>([]);
const guildSelectedTagIds = ref<number[]>([]);
const tagsSaving = ref(false);
const tagsError = ref<string | null>(null);
const tagComboInputId = computed(
  () => `guild-roster-tags-${guildId.value}-${characterId.value}`
);

const GUILD_ROLE_SLUG_LEADER = 'leader';

const isGuildLeaderCharacter = computed(
  () =>
    guild.value?.leader_character_id != null &&
    Number(guild.value.leader_character_id) === characterId.value
);

/** Роль из справочника со слагом leader — не назначается через смену роли участника. */
const hasMemberLeaderRoleSlug = computed(
  () => member.value?.guild_role?.slug === GUILD_ROLE_SLUG_LEADER
);

/** Не дергать API при программной подстановке тегов из ответа сервера. */
let suppressTagsAutosave = false;
let guildTagsSaveSeq = 0;

function toRosterTagItem(t: {
  id: number;
  name: string;
  slug: string;
  used_by_user_id?: number | null;
  used_by_guild_id?: number | null;
}): RosterTagItem {
  const full = allTags.value.find((a) => a.id === t.id);
  return {
    id: t.id,
    name: t.name,
    slug: t.slug,
    used_by_user_id: full?.used_by_user_id ?? t.used_by_user_id ?? null,
    used_by_guild_id: full?.used_by_guild_id ?? t.used_by_guild_id ?? null,
  };
}

/** Единый список для отображения: гильдейские (редактируемые id) + личные без дубля. */
const displayTagRows = computed((): RosterTagRow[] => {
  const m = member.value;
  if (!m) return [];
  const guildIds = new Set(guildSelectedTagIds.value);
  const rows: RosterTagRow[] = [];
  for (const id of guildSelectedTagIds.value) {
    const raw = m.tags.find((t) => t.id === id) ?? allTags.value.find((t) => t.id === id);
    if (raw) {
      rows.push({ tag: toRosterTagItem(raw), source: 'guild' });
    }
  }
  for (const t of m.personal_tags ?? []) {
    if (!guildIds.has(t.id)) {
      rows.push({ tag: toRosterTagItem(t), source: 'personal' });
    }
  }
  return sortRosterTagRows(rows);
});

const displayTagRowsUi = computed(() => sliceRosterTagRowsForDisplay(displayTagRows.value));

const roleOptions = computed<SelectOption[]>(() =>
  guildRoles.value
    .filter((r) => r.slug !== GUILD_ROLE_SLUG_LEADER)
    .map((r) => ({ value: String(r.id), label: r.name }))
);

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

function backToRoster() {
  router.push({
    name: 'guild-roster',
    params: { id: String(guildId.value) },
  });
}

function applyMemberGuildTags(m: GuildRosterMember) {
  guildSelectedTagIds.value = m.tags.map((t) => t.id);
}

/** Только теги этой гильдии (для комбобокса «Добавить тег гильдии»). */
function filterGuildTagsOnly(list: Tag[], gid: number): Tag[] {
  return list
    .filter((t) => t.used_by_guild_id != null && Number(t.used_by_guild_id) === gid)
    .sort((a, b) => a.name.localeCompare(b.name, 'ru'));
}

async function loadData() {
  if (!guildId.value || !characterId.value || Number.isNaN(guildId.value) || Number.isNaN(characterId.value)) {
    loading.value = false;
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    const [guildData, rosterMemberRes, tagsList] = await Promise.all([
      guildsApi.getGuild(guildId.value),
      guildsApi.getGuildRosterMember(guildId.value, characterId.value),
      tagsApi.getTags(false, guildId.value),
    ]);
    guild.value = guildData;
    member.value = rosterMemberRes.data;
    allTags.value = filterGuildTagsOnly(tagsList, guildId.value);
    suppressTagsAutosave = true;
    guildTagsSaveSeq += 1;
    applyMemberGuildTags(rosterMemberRes.data);
    await nextTick();
    suppressTagsAutosave = false;
    canExclude.value = rosterMemberRes.can_exclude;
    canChangeRole.value = rosterMemberRes.can_change_role;
    canEditGuildTags.value = rosterMemberRes.can_edit_guild_tags;
    canCreateGuildTag.value = rosterMemberRes.can_create_guild_tag;
    canDeleteGuildTag.value = rosterMemberRes.can_delete_guild_tag;
    selectedRoleId.value = rosterMemberRes.data.guild_role
      ? String(rosterMemberRes.data.guild_role.id)
      : '';
    if (rosterMemberRes.can_change_role) {
      const rolesResult = await guildsApi.getGuildRoles(guildId.value);
      guildRoles.value = rolesResult.roles;
      if (rosterMemberRes.data.guild_role?.slug === GUILD_ROLE_SLUG_LEADER) {
        selectedRoleId.value = '';
      }
    } else {
      guildRoles.value = [];
    }
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) {
      error.value = 'Участник не найден в составе гильдии.';
    } else {
      error.value = err instanceof Error ? err.message : 'Ошибка загрузки';
    }
    member.value = null;
  } finally {
    loading.value = false;
  }
}

function openExcludeDialog() {
  excludeError.value = null;
  excludeDialogOpen.value = true;
}

function closeExcludeDialog() {
  if (!excluding.value) {
    excludeDialogOpen.value = false;
    excludeError.value = null;
  }
}

async function confirmExclude() {
  if (!guildId.value || !characterId.value || excluding.value) return;
  excluding.value = true;
  excludeError.value = null;
  try {
    await guildsApi.excludeGuildMember(guildId.value, characterId.value);
    excludeDialogOpen.value = false;
    backToRoster();
  } catch (e) {
    excludeError.value = e instanceof Error ? e.message : 'Не удалось исключить участника';
  } finally {
    excluding.value = false;
  }
}

const tagDeleteDialogOpen = ref(false);
const tagToDelete = ref<Tag | null>(null);
const tagDeleteLoading = ref(false);
const tagDeleteError = ref<string | null>(null);

/** Удаление тега доступно только для гильдейского тега этой гильдии при наличии права. */
function canDeleteTagHandler(tag: Tag): boolean {
  if (!canDeleteGuildTag.value) return false;
  return tag.used_by_guild_id != null && Number(tag.used_by_guild_id) === guildId.value;
}

function openTagDeleteConfirm(tag: Tag) {
  tagToDelete.value = tag;
  tagDeleteError.value = null;
  tagDeleteDialogOpen.value = true;
}

async function confirmDeleteGuildTag() {
  const t = tagToDelete.value;
  if (!t || tagDeleteLoading.value) return;
  tagDeleteLoading.value = true;
  tagDeleteError.value = null;
  try {
    await tagsApi.deleteGuildTag(guildId.value, t.id);
    suppressTagsAutosave = true;
    guildTagsSaveSeq += 1;
    guildSelectedTagIds.value = guildSelectedTagIds.value.filter((id) => id !== t.id);
    allTags.value = allTags.value.filter((x) => x.id !== t.id);
    if (member.value) {
      member.value = {
        ...member.value,
        tags: member.value.tags.filter((x) => x.id !== t.id),
        personal_tags: (member.value.personal_tags ?? []).filter((x) => x.id !== t.id),
      };
    }
    await nextTick();
    suppressTagsAutosave = false;
    tagDeleteDialogOpen.value = false;
    tagToDelete.value = null;
  } catch (e) {
    tagDeleteError.value = e instanceof Error ? e.message : 'Не удалось удалить тег';
  } finally {
    tagDeleteLoading.value = false;
  }
}

async function onRoleChange(newRoleId: string) {
  if (!guildId.value || !characterId.value || changingRole.value) return;
  const id = Number(newRoleId);
  if (Number.isNaN(id) || id === member.value?.guild_role?.id) return;
  changingRole.value = true;
  roleError.value = null;
  try {
    await guildsApi.updateGuildMemberRole(guildId.value, characterId.value, id);
    const role = guildRoles.value.find((r) => r.id === id);
    if (member.value && role) {
      member.value = {
        ...member.value,
        guild_role: { id: role.id, name: role.name, slug: role.slug },
      };
    }
  } catch (e) {
    roleError.value = e instanceof Error ? e.message : 'Не удалось изменить роль';
    if (member.value?.guild_role?.slug === GUILD_ROLE_SLUG_LEADER && canChangeRole.value) {
      selectedRoleId.value = '';
    } else {
      selectedRoleId.value = member.value?.guild_role ? String(member.value.guild_role.id) : '';
    }
  } finally {
    changingRole.value = false;
  }
}

watchDebounced(
  guildSelectedTagIds,
  async (ids) => {
    if (suppressTagsAutosave || !canEditGuildTags.value || !member.value) return;
    const gid = guildId.value;
    const cid = characterId.value;
    if (!gid || !cid) return;

    const sortedIds = [...ids].sort((a, b) => a - b);
    const sortedServer = [...member.value.tags.map((t) => t.id)].sort((a, b) => a - b);
    if (
      sortedIds.length === sortedServer.length &&
      sortedIds.every((v, i) => v === sortedServer[i])
    ) {
      return;
    }

    const seq = ++guildTagsSaveSeq;
    tagsSaving.value = true;
    tagsError.value = null;
    try {
      await guildsApi.updateGuildMemberTags(gid, cid, [...ids]);
      if (seq !== guildTagsSaveSeq) return;
      const nextTags = [...ids]
        .map((id) => allTags.value.find((t) => t.id === id))
        .filter((t): t is Tag => t !== undefined)
        .map((t) => ({
          id: t.id,
          name: t.name,
          slug: t.slug,
          used_by_user_id: t.used_by_user_id ?? null,
          used_by_guild_id: t.used_by_guild_id ?? null,
        }));
      member.value = { ...member.value, tags: nextTags };
    } catch (e) {
      if (seq === guildTagsSaveSeq) {
        tagsError.value = e instanceof Error ? e.message : 'Не удалось сохранить теги';
      }
    } finally {
      if (seq === guildTagsSaveSeq) {
        tagsSaving.value = false;
      }
    }
  },
  { debounce: 400, deep: true }
);

onMounted(() => loadData());
watch([guildId, characterId], () => loadData());
</script>

<template>
  <div class="container py-4 md:py-6 max-w-2xl mx-auto">
    <div class="mb-4">
      <Button variant="ghost" size="sm" class="-ml-2 shrink-0" @click="backToRoster">
        ← К составу гильдии
      </Button>
    </div>

    <Card v-if="error">
      <CardContent class="pt-6">
        <p class="text-sm text-destructive">{{ error }}</p>
        <Button class="mt-4" variant="outline" @click="backToRoster">К составу</Button>
      </CardContent>
    </Card>

    <Card v-else-if="loading">
      <CardContent class="py-8">
        <p class="text-sm text-muted-foreground">Загрузка…</p>
      </CardContent>
    </Card>

    <Card v-else-if="member" class="overflow-hidden">
      <CardHeader class="flex flex-row items-start justify-between gap-4">
        <div class="flex items-center gap-4">
          <Avatar
            :src="member.avatar_url ?? undefined"
            :alt="member.name"
            :fallback="avatarFallback(member.name)"
            class="h-16 w-16 shrink-0 md:h-20 md:w-20"
          />
          <div class="min-w-0 flex-1">
            <CardTitle class="text-xl">{{ member.name }}</CardTitle>
            <p v-if="isGuildLeaderCharacter" class="mt-1 text-xs text-muted-foreground">
              Лидер гильдии назначается только в разделе «Настройки» этой гильдии.
            </p>
            <div v-if="canChangeRole && roleOptions.length" class="mt-2 flex flex-col gap-2">
              <template v-if="hasMemberLeaderRoleSlug">
                <Badge variant="secondary" class="w-fit">{{ member.guild_role!.name }}</Badge>
                <p class="text-xs text-muted-foreground">
                  Роль «Лидер» нельзя выбрать в этом списке — лидер задаётся в настройках гильдии. Выберите
                  другую роль, чтобы снять эту с участника.
                </p>
              </template>
              <div class="flex flex-wrap items-center gap-2">
                <Select
                  v-model="selectedRoleId"
                  :options="roleOptions"
                  :placeholder="hasMemberLeaderRoleSlug ? 'Сменить на другую роль' : 'Роль'"
                  :disabled="changingRole"
                  trigger-class="w-[180px]"
                  @update:model-value="onRoleChange"
                />
                <span v-if="changingRole" class="text-xs text-muted-foreground">Сохранение…</span>
                <p v-if="roleError" class="text-xs text-destructive">{{ roleError }}</p>
              </div>
            </div>
            <Badge v-else-if="member.guild_role" variant="secondary" class="mt-1">
              {{ member.guild_role.name }}
            </Badge>
          </div>
        </div>
        <Button
          v-if="canExclude"
          variant="destructive"
          size="sm"
          class="shrink-0"
          :disabled="excluding"
          @click="openExcludeDialog"
        >
          Исключить из гильдии
        </Button>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="member.game_classes.length > 0">
          <p class="mb-1 text-sm font-medium text-muted-foreground">Классы</p>
          <div class="flex flex-wrap gap-1">
            <Badge
              v-for="gc in member.game_classes"
              :key="gc.id"
              variant="outline"
              class="text-xs"
            >
              {{ gc.name_ru ?? gc.name }}
            </Badge>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-sm font-medium text-muted-foreground">Теги</p>
          <div v-if="displayTagRows.length > 0" class="flex flex-wrap items-center gap-2">
            <template v-for="row in displayTagRowsUi.visible" :key="row.source + '-' + row.tag.id">
              <button
                v-if="canEditGuildTags && row.source === 'guild'"
                type="button"
                class="inline-flex rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                @click="guildSelectedTagIds = guildSelectedTagIds.filter((x) => x !== row.tag.id)"
              >
                <Badge
                  variant="outline"
                  :class="[rosterTagBadgeClass('guild', row.tag), 'pr-1 text-xs']"
                >
                  {{ row.tag.name }}
                  <span class="ml-1 opacity-70" aria-hidden="true">×</span>
                </Badge>
              </button>
              <Badge
                v-else
                variant="outline"
                :class="[rosterTagBadgeClass(row.source, row.tag), 'text-xs']"
              >
                {{ row.tag.name }}
              </Badge>
            </template>
            <span
              v-if="displayTagRowsUi.moreCount > 0"
              class="text-xs text-muted-foreground"
              :title="`Ещё ${displayTagRowsUi.moreCount} тегов`"
            >
              +{{ displayTagRowsUi.moreCount }}
            </span>
          </div>
          <p v-else-if="!canEditGuildTags" class="text-sm text-muted-foreground">Нет тегов</p>
          <template v-if="canEditGuildTags">
            <TagAddCombobox
              v-model:all-tags="allTags"
              v-model:selected-tag-ids="guildSelectedTagIds"
              :input-id="tagComboInputId"
              :allow-create-tag="canCreateGuildTag"
              :tag-create-guild-id="guildId"
              :can-delete-tag="canDeleteTagHandler"
              label="Добавить тег гильдии"
              placeholder="Выберите или введите тег"
              @delete-tag="openTagDeleteConfirm"
            />
            <div class="flex flex-wrap items-center gap-2">
              <span v-if="tagsSaving" class="text-xs text-muted-foreground">Сохранение…</span>
              <p v-if="tagsError" class="text-xs text-destructive">{{ tagsError }}</p>
            </div>
          </template>
        </div>
      </CardContent>
    </Card>

    <ConfirmDialog
      :open="excludeDialogOpen"
      title="Исключить из гильдии"
      confirm-label="Исключить"
      confirm-variant="destructive"
      :loading="excluding"
      @confirm="confirmExclude"
      @update:open="(v) => { if (!v) closeExcludeDialog(); }"
    >
      <template #description>
        <p v-if="member && guild">
          Исключить персонажа
          <span class="font-medium text-foreground">{{ member.name }}</span>
          из гильдии
          <span class="font-medium text-foreground">«{{ guild.name }}»</span>?
          Это действие нельзя отменить.
        </p>
        <p v-else>Исключить этого участника из гильдии? Это действие нельзя отменить.</p>
        <p v-if="excludeError" class="mt-2 text-sm text-destructive">{{ excludeError }}</p>
      </template>
    </ConfirmDialog>

    <ConfirmDialog
      :open="tagDeleteDialogOpen"
      title="Удалить тег гильдии"
      confirm-label="Удалить"
      confirm-variant="destructive"
      :loading="tagDeleteLoading"
      @confirm="confirmDeleteGuildTag"
      @update:open="(v) => { if (!v && !tagDeleteLoading) { tagDeleteDialogOpen = false; tagToDelete = null; tagDeleteError = null; } }"
    >
      <template #description>
        <p v-if="tagToDelete">
          Удалить тег
          <span class="font-medium text-foreground">«{{ tagToDelete.name }}»</span>
          из гильдии? Тег будет снят со всех участников. Это действие нельзя отменить.
        </p>
        <p v-else>Удалить тег из гильдии? Это действие нельзя отменить.</p>
        <p v-if="tagDeleteError" class="mt-2 text-sm text-destructive">{{ tagDeleteError }}</p>
      </template>
    </ConfirmDialog>
  </div>
</template>
