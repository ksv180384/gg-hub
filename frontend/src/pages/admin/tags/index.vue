<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { Button, Input, Select, Spinner } from '@/shared/ui';
import type { SelectOption } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { useAuthStore } from '@/stores/auth';
import { tagsApi, type Tag, PERMISSION_TAG_EDIT, PERMISSION_TAG_HIDE, PERMISSION_TAG_DELETE } from '@/shared/api/tagsApi';
import TagCard from './components/TagCard.vue';

const auth = useAuthStore();
const canEdit = () => auth.hasPermission(PERMISSION_TAG_EDIT);
const canHide = () => auth.hasPermission(PERMISSION_TAG_HIDE);
const canDelete = () => auth.hasPermission(PERMISSION_TAG_DELETE);

const tags = ref<Tag[]>([]);
const loading = ref(true);
/** Загрузка списка с сервера, пока видна панель фильтров */
const filtersLoading = ref(false);
const error = ref<string | null>(null);
const togglingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const tagToDelete = ref<Tag | null>(null);
const tagSearchQuery = ref('');
/** Подстрока в названии гильдии — остаются только теги этой / этих гильдий. */
const guildNameQuery = ref('');
/** Подстрока в имени пользователя — остаются только личные теги этого пользователя. */
const userNameQuery = ref('');
/** Все теги | только общие | только гильдии | только пользователи */
const tagKindFilter = ref<'all' | 'common' | 'guild' | 'user'>('all');

const tagKindFilterOptions: SelectOption[] = [
  { value: 'all', label: 'Все теги' },
  { value: 'common', label: 'Общие теги' },
  { value: 'guild', label: 'Теги гильдий' },
  { value: 'user', label: 'Теги пользователей' },
];

/** Общие → теги гильдий → личные теги; внутри группы — по имени. */
const tagSections = computed(() => {
  const common: Tag[] = [];
  const guild: Tag[] = [];
  const user: Tag[] = [];
  for (const t of tags.value) {
    if (t.used_by_guild_id != null) {
      guild.push(t);
    } else if (t.used_by_user_id != null) {
      user.push(t);
    } else {
      common.push(t);
    }
  }
  const byName = (a: Tag, b: Tag) => a.name.localeCompare(b.name, 'ru');
  common.sort(byName);
  guild.sort(byName);
  user.sort(byName);
  const out: { key: string; label: string; tags: Tag[] }[] = [];
  if (common.length) {
    out.push({ key: 'common', label: 'Общие теги', tags: common });
  }
  if (guild.length) {
    out.push({ key: 'guild', label: 'Теги гильдий', tags: guild });
  }
  if (user.length) {
    out.push({ key: 'user', label: 'Теги пользователей', tags: user });
  }
  return out;
});

const filtersActive = computed(
  () =>
    !!tagSearchQuery.value.trim() ||
    !!guildNameQuery.value.trim() ||
    !!userNameQuery.value.trim() ||
    tagKindFilter.value !== 'all',
);

function buildAdminListFilters() {
  return {
    kind: tagKindFilter.value,
    tagName: tagSearchQuery.value,
    guildName: guildNameQuery.value,
    userName: userNameQuery.value,
  };
}

let loadSeq = 0;
let loadDebounce: ReturnType<typeof setTimeout> | null = null;

async function loadTags(silent = false) {
  const seq = ++loadSeq;
  if (!silent) {
    loading.value = true;
  }
  filtersLoading.value = true;
  error.value = null;
  try {
    tags.value = await tagsApi.getTags(true, undefined, buildAdminListFilters());
    if (seq !== loadSeq) {
      return;
    }
  } catch (e) {
    if (seq !== loadSeq) {
      return;
    }
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки тегов';
  } finally {
    if (seq === loadSeq) {
      filtersLoading.value = false;
      if (!silent) {
        loading.value = false;
      }
    }
  }
}

function scheduleLoadTags() {
  if (loadDebounce) {
    clearTimeout(loadDebounce);
  }
  loadDebounce = setTimeout(() => {
    loadDebounce = null;
    void loadTags(true);
  }, 300);
}

watch([tagSearchQuery, guildNameQuery, userNameQuery, tagKindFilter], () => {
  scheduleLoadTags();
});

async function toggleHidden(tag: Tag) {
  if (togglingId.value !== null) return;
  togglingId.value = tag.id;
  try {
    await tagsApi.updateTag(tag.id, { is_hidden: !tag.is_hidden });
    tag.is_hidden = !tag.is_hidden;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка обновления';
  } finally {
    togglingId.value = null;
  }
}

function openDeleteDialog(tag: Tag) {
  tagToDelete.value = tag;
  deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
  if (!deletingId.value) {
    tagToDelete.value = null;
    deleteDialogOpen.value = false;
  }
}

async function confirmDeleteTag() {
  const tag = tagToDelete.value;
  if (!tag) return;
  deletingId.value = tag.id;
  error.value = null;
  try {
    await tagsApi.deleteTag(tag.id);
    tags.value = tags.value.filter((t) => t.id !== tag.id);
    tagToDelete.value = null;
    deleteDialogOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка удаления';
  } finally {
    deletingId.value = null;
  }
}

onMounted(() => {
  void loadTags(false);
});

onUnmounted(() => {
  if (loadDebounce) {
    clearTimeout(loadDebounce);
  }
});

const emptyTagsHint = computed(() => {
  if (tagSearchQuery.value.trim()) {
    return 'По запросу ничего не найдено.';
  }
  if (guildNameQuery.value.trim()) {
    return 'Нет тегов гильдий с таким названием.';
  }
  if (userNameQuery.value.trim()) {
    return 'Нет тегов пользователей с таким именем.';
  }
  if (tagKindFilter.value !== 'all') {
    return 'Нет тегов выбранного типа.';
  }
  return 'Ничего не найдено.';
});
</script>

<template>
  <div class="container py-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-xl font-semibold">Теги</h1>
      <RouterLink to="/admin/tags/create">
        <Button size="sm">Добавить тег</Button>
      </RouterLink>
    </div>
    <p v-if="error" class="mb-4 text-sm text-destructive">{{ error }}</p>
    <div v-else-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <p v-else-if="!tags.length && !filtersActive" class="text-sm text-muted-foreground">
      Нет тегов. Теги можно добавлять к гильдиям и персонажам.
    </p>
    <div v-else class="space-y-4">
      <div
        class="relative flex flex-col gap-3 rounded-lg border border-border/60 bg-muted/20 p-3 sm:flex-row sm:flex-wrap sm:items-end"
        :aria-busy="filtersLoading"
      >
        <div class="w-full min-w-0 sm:max-w-sm sm:flex-1">
          <label for="tags-search" class="mb-1.5 block text-xs text-muted-foreground">Поиск по названию</label>
          <Input
            id="tags-search"
            v-model="tagSearchQuery"
            type="search"
            placeholder="Введите название…"
            aria-label="Поиск тегов по названию"
            autocomplete="off"
          />
        </div>
        <div class="w-full min-w-0 sm:max-w-xs">
          <p class="mb-1.5 text-xs text-muted-foreground">Тип тегов</p>
          <Select
            v-model="tagKindFilter"
            :options="tagKindFilterOptions"
            placeholder="Выберите тип"
            trigger-class="w-full"
          />
        </div>
        <div class="w-full min-w-0 sm:max-w-sm sm:flex-1">
          <label for="tags-guild-name" class="mb-1.5 block text-xs text-muted-foreground">Название гильдии</label>
          <Input
            id="tags-guild-name"
            v-model="guildNameQuery"
            type="search"
            placeholder="Часть названия гильдии…"
            aria-label="Фильтр тегов по названию гильдии"
            autocomplete="off"
          />
        </div>
        <div class="w-full min-w-0 sm:max-w-sm sm:flex-1">
          <label for="tags-user-name" class="mb-1.5 block text-xs text-muted-foreground">Имя пользователя</label>
          <Input
            id="tags-user-name"
            v-model="userNameQuery"
            type="search"
            placeholder="Часть имени пользователя…"
            aria-label="Фильтр тегов по имени пользователя"
            autocomplete="off"
          />
        </div>
        <div
          v-if="filtersLoading"
          class="flex shrink-0 items-center gap-2 self-end sm:ml-auto"
          role="status"
          aria-live="polite"
        >
          <Spinner class="h-4 w-4 text-muted-foreground" />
          <span class="text-xs text-muted-foreground">Загрузка…</span>
        </div>
      </div>
      <p v-if="!tagSections.length" class="text-sm text-muted-foreground">
        {{ emptyTagsHint }}
      </p>
      <template v-else>
        <section v-for="section in tagSections" :key="section.key" class="space-y-1.5">
          <h2 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {{ section.label }}
          </h2>
          <TagCard
            v-for="tag in section.tags"
            :key="tag.id"
            :tag="tag"
            :toggling="togglingId === tag.id"
            :can-edit="canEdit()"
            :can-hide="canHide()"
            :can-delete="canDelete()"
            @toggle-hidden="toggleHidden(tag)"
            @delete="openDeleteDialog(tag)"
          />
        </section>
      </template>
    </div>

    <ConfirmDialog
      :open="deleteDialogOpen"
      title="Удалить тег?"
      :description="tagToDelete ? `Тег «${tagToDelete.name}» будет удалён. Связи с гильдиями и персонажами будут сняты. Это действие нельзя отменить.` : ''"
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="deletingId === tagToDelete?.id"
      @update:open="(v) => { deleteDialogOpen = v; if (!v) closeDeleteDialog(); }"
      @confirm="confirmDeleteTag"
    />
  </div>
</template>
