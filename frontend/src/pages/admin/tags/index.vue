<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Button } from '@/shared/ui';
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
const error = ref<string | null>(null);
const togglingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const tagToDelete = ref<Tag | null>(null);

async function loadTags() {
  loading.value = true;
  error.value = null;
  try {
    tags.value = await tagsApi.getTags(true);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки тегов';
  } finally {
    loading.value = false;
  }
}

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

onMounted(() => loadTags());
</script>

<template>
  <div class="container py-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Теги</h1>
      <RouterLink to="/admin/tags/create">
        <Button>Добавить тег</Button>
      </RouterLink>
    </div>
    <p v-if="error" class="mb-4 text-sm text-destructive">{{ error }}</p>
    <div v-else-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <div v-else class="space-y-4">
      <TagCard
        v-for="tag in tags"
        :key="tag.id"
        :tag="tag"
        :toggling="togglingId === tag.id"
        :can-edit="canEdit()"
        :can-hide="canHide()"
        :can-delete="canDelete()"
        @toggle-hidden="toggleHidden(tag)"
        @delete="openDeleteDialog(tag)"
      />
      <p v-if="!tags.length" class="text-sm text-muted-foreground">
        Нет тегов. Теги можно добавлять к гильдиям и персонажам.
      </p>
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
