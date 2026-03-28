<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Avatar, Badge, Button, Input, Label } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import {
  applicationCommentsAdminApi,
  type AdminApplicationCommentItem,
} from '@/shared/api/commentsApi';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const PERMISSION_HIDE = 'skryvat-kommentarii';
const PERMISSION_DELETE = 'udaliat-kommentarii';

const canHide = computed(() => auth.hasPermission(PERMISSION_HIDE));
const canDelete = computed(() => auth.hasPermission(PERMISSION_DELETE));

const comments = ref<AdminApplicationCommentItem[]>([]);
const meta = ref<{ current_page: number; last_page: number; per_page: number; total: number } | null>(null);
const loading = ref(true);
const actionLoadingId = ref<number | null>(null);
const deleteDialog = ref<{ open: boolean; comment: AdminApplicationCommentItem | null }>({ open: false, comment: null });
const hideDialog = ref<{ open: boolean; comment: AdminApplicationCommentItem | null }>({ open: false, comment: null });
const hideReason = ref('');
const hideSubmitting = ref(false);
const deleteReason = ref('');
const applicationFilter = ref('');

async function loadComments(page = 1) {
  loading.value = true;
  try {
    const applicationId = Number(applicationFilter.value);
    const res = await applicationCommentsAdminApi.getAdminApplicationComments({
      page,
      per_page: 20,
      application_id: Number.isInteger(applicationId) && applicationId > 0 ? applicationId : undefined,
    });
    comments.value = res.data;
    meta.value = res.meta;
  } catch {
    comments.value = [];
    meta.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(() => loadComments());

function goToApplication(c: AdminApplicationCommentItem) {
  if (c.guild_id == null) return;
  router.push({
    name: 'guild-application-show',
    params: { id: String(c.guild_id), applicationId: String(c.application_id) },
  });
}

function openHideDialog(c: AdminApplicationCommentItem) {
  hideDialog.value = { open: true, comment: c };
  hideReason.value = '';
}

function closeHideDialog() {
  hideDialog.value = { open: false, comment: null };
  hideReason.value = '';
}

async function hideCommentWithReason() {
  const c = hideDialog.value.comment;
  const reason = hideReason.value.trim();
  if (!c || !canHide.value || !reason) return;
  if (!canHide.value) return;
  actionLoadingId.value = c.id;
  hideSubmitting.value = true;
  try {
    const updated = await applicationCommentsAdminApi.hideAdminApplicationComment(c.id, reason);
    const idx = comments.value.findIndex((x) => x.id === c.id);
    if (idx !== -1) comments.value[idx] = updated;
    closeHideDialog();
  } finally {
    actionLoadingId.value = null;
    hideSubmitting.value = false;
  }
}

async function unhideComment(c: AdminApplicationCommentItem) {
  if (!canHide.value) return;
  actionLoadingId.value = c.id;
  try {
    const updated = await applicationCommentsAdminApi.unhideAdminApplicationComment(c.id);
    const idx = comments.value.findIndex((x) => x.id === c.id);
    if (idx !== -1) comments.value[idx] = updated;
  } finally {
    actionLoadingId.value = null;
  }
}

function openDeleteDialog(c: AdminApplicationCommentItem) {
  deleteDialog.value = { open: true, comment: c };
  deleteReason.value = '';
}

function closeDeleteDialog() {
  deleteDialog.value = { open: false, comment: null };
  deleteReason.value = '';
}

const deleteSubmitting = ref(false);
async function confirmDelete() {
  const c = deleteDialog.value.comment;
  const reason = deleteReason.value.trim();
  if (!c || !canDelete.value) {
    closeDeleteDialog();
    return;
  }
  if (!reason) return;
  deleteSubmitting.value = true;
  try {
    await applicationCommentsAdminApi.deleteAdminApplicationComment(c.id, reason);
    const idx = comments.value.findIndex((x) => x.id === c.id);
    if (idx !== -1) {
      comments.value[idx] = {
        ...comments.value[idx],
        is_deleted: true,
        delete_reason: reason,
      };
    }
    closeDeleteDialog();
  } finally {
    deleteSubmitting.value = false;
  }
}

const currentPage = computed(() => meta.value?.current_page ?? 1);
const lastPage = computed(() => meta.value?.last_page ?? 1);
const total = computed(() => meta.value?.total ?? 0);
</script>

<template>
  <div class="container py-6 space-y-4 max-w-3xl mx-auto">
    <h1 class="text-xl font-semibold">Модерация комментариев заявок</h1>
    <p class="text-sm text-muted-foreground">
      Все комментарии к заявкам в гильдии в одном списке. Можно фильтровать по ID заявки.
    </p>

    <div class="space-y-2">
      <Label for="application-filter">ID заявки</Label>
      <div class="flex gap-2">
        <Input
          id="application-filter"
          v-model="applicationFilter"
          type="number"
          placeholder="Например: 5"
          class="w-full"
        />
        <Button variant="outline" @click="loadComments(1)">Применить</Button>
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
    <p v-else-if="comments.length === 0" class="text-sm text-muted-foreground">
      Комментариев пока нет.
    </p>
    <ul v-else class="space-y-4">
      <li
        v-for="c in comments"
        :key="c.id"
        class="rounded-lg border border-border bg-card p-4 space-y-2"
      >
        <div class="flex items-start gap-3">
          <Avatar
            class="h-9 w-9 shrink-0"
            :src="c.author_avatar_url ?? undefined"
            :alt="c.author_name"
          />
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-2 text-sm">
              <span class="font-medium">{{ c.author_name }}</span>
              <span class="text-muted-foreground">{{ formatRelativeTime(c.created_at) }}</span>
              <Badge v-if="c.is_hidden" variant="secondary" class="text-xs">Комментарий скрыт</Badge>
              <Badge v-if="c.is_deleted" variant="destructive" class="text-xs">Удалён</Badge>
            </div>
            <button
              v-if="c.guild_id != null"
              type="button"
              class="mt-1 text-left text-xs text-muted-foreground hover:text-foreground hover:underline"
              @click="goToApplication(c)"
            >
              {{ c.guild_name || 'Гильдия' }} — заявка #{{ c.application_id }}
            </button>
            <p class="mt-2 whitespace-pre-wrap break-words text-sm">
              {{ c.body }}
            </p>
            <p v-if="c.hidden_reason" class="mt-1 text-xs text-muted-foreground">
              Причина скрытия: {{ c.hidden_reason }}
            </p>
            <p v-if="c.delete_reason" class="mt-1 text-xs text-muted-foreground">
              Причина удаления: {{ c.delete_reason }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-1 pt-2 border-t border-border">
          <Button
            v-if="canHide && c.is_hidden"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 text-muted-foreground hover:text-foreground"
            :disabled="actionLoadingId !== null || c.is_deleted"
            title="Показать комментарий"
            @click="unhideComment(c)"
          >
            <span v-if="actionLoadingId === c.id" class="text-xs">…</span>
            <svg v-else class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </Button>
          <Button
            v-if="canHide && !c.is_hidden"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 text-muted-foreground hover:text-foreground"
            :disabled="actionLoadingId !== null || c.is_deleted"
            title="Скрыть комментарий"
            @click="openHideDialog(c)"
          >
            <span v-if="actionLoadingId === c.id" class="text-xs">…</span>
            <svg v-else class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
              <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
              <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
              <line x1="2" x2="22" y1="2" y2="22" />
            </svg>
          </Button>
          <Button
            v-if="canDelete"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
            :disabled="actionLoadingId !== null || c.is_deleted"
            title="Удалить комментарий"
            @click="openDeleteDialog(c)"
          >
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 6h18" />
              <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
              <line x1="10" x2="10" y1="11" y2="17" />
              <line x1="14" x2="14" y1="11" y2="17" />
            </svg>
          </Button>
        </div>
      </li>
    </ul>

    <div v-if="meta && lastPage > 1" class="flex items-center justify-center gap-2 pt-4">
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage <= 1 || loading"
        @click="loadComments(currentPage - 1)"
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
        @click="loadComments(currentPage + 1)"
      >
        Вперёд
      </Button>
    </div>

    <ConfirmDialog
      :open="hideDialog.open"
      title="Скрыть комментарий"
      confirm-label="Скрыть"
      :loading="hideSubmitting"
      @confirm="hideCommentWithReason"
      @update:open="(v) => { if (!v) closeHideDialog(); }"
    >
      <template #description>
        <div class="space-y-2">
          <p>Укажите причину скрытия. Автор комментария получит уведомление с этой причиной.</p>
          <Label for="hide-reason">Причина *</Label>
          <textarea
            id="hide-reason"
            v-model="hideReason"
            class="min-h-[90px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            placeholder="Например: оскорбления, мат, оффтоп…"
            maxlength="1000"
          />
        </div>
      </template>
    </ConfirmDialog>

    <ConfirmDialog
      :open="deleteDialog.open"
      title="Удалить комментарий"
      confirm-label="Удалить"
      confirm-variant="destructive"
      :loading="deleteSubmitting"
      @confirm="confirmDelete"
      @update:open="(v) => { if (!v) closeDeleteDialog(); }"
    >
      <template #description>
        <div class="space-y-2">
          <p>Комментарий будет помечен как удалённый. Укажите причину удаления.</p>
          <Label for="delete-reason">Причина *</Label>
          <textarea
            id="delete-reason"
            v-model="deleteReason"
            class="min-h-[90px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            placeholder="Например: токсичное поведение, нарушение правил…"
            maxlength="1000"
          />
        </div>
      </template>
    </ConfirmDialog>
  </div>
</template>
