<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Avatar, Badge, Button, Input, Label } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import { commentsApi, type AdminPostCommentItem } from '@/shared/api/commentsApi';
import { postsApi, type AdminPostSuggestItem } from '@/shared/api/postsApi';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const PERMISSION_HIDE = 'skryvat-kommentarii';
const PERMISSION_DELETE = 'udaliat-kommentarii';

const canHide = computed(() => auth.hasPermission(PERMISSION_HIDE));
const canDelete = computed(() => auth.hasPermission(PERMISSION_DELETE));

const comments = ref<AdminPostCommentItem[]>([]);
const meta = ref<{ current_page: number; last_page: number; per_page: number; total: number } | null>(null);
const loading = ref(true);
const actionLoadingId = ref<number | null>(null);
const deleteDialog = ref<{ open: boolean; comment: AdminPostCommentItem | null }>({ open: false, comment: null });

const postSearchQuery = ref('');
const suggestions = ref<AdminPostSuggestItem[]>([]);
const suggestionsLoading = ref(false);
const selectedPost = ref<AdminPostSuggestItem | null>(null);
let suggestTimeout: ReturnType<typeof setTimeout> | null = null;

async function loadComments(page = 1) {
  loading.value = true;
  try {
    const res = await commentsApi.getAdminComments({
      page,
      per_page: 20,
      post_id: selectedPost.value?.id,
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

watch(postSearchQuery, (q) => {
  if (suggestTimeout) clearTimeout(suggestTimeout);
  const trimmed = q.trim();
  if (!trimmed) {
    suggestions.value = [];
    return;
  }
  suggestTimeout = setTimeout(async () => {
    suggestTimeout = null;
    suggestionsLoading.value = true;
    try {
      suggestions.value = await postsApi.getAdminPostsSuggest(trimmed);
    } catch {
      suggestions.value = [];
    } finally {
      suggestionsLoading.value = false;
    }
  }, 300);
});

function selectPost(post: AdminPostSuggestItem) {
  selectedPost.value = post;
  postSearchQuery.value = '';
  suggestions.value = [];
  loadComments(1);
}

function clearPostFilter() {
  selectedPost.value = null;
  loadComments(1);
}

onMounted(() => loadComments());

function postLink(c: AdminPostCommentItem) {
  if (c.guild_id != null) {
    return {
      name: 'guild-post-show' as const,
      params: { id: String(c.guild_id), postId: String(c.post_id) },
      hash: '#comments',
    };
  }
  return null;
}

function goToPost(c: AdminPostCommentItem) {
  const link = postLink(c);
  if (link) router.push(link);
}

async function hideComment(c: AdminPostCommentItem) {
  if (!canHide.value) return;
  actionLoadingId.value = c.id;
  try {
    const updated = await commentsApi.hideAdminComment(c.id);
    const idx = comments.value.findIndex((x) => x.id === c.id);
    if (idx !== -1) comments.value[idx] = updated;
  } finally {
    actionLoadingId.value = null;
  }
}

async function unhideComment(c: AdminPostCommentItem) {
  if (!canHide.value) return;
  actionLoadingId.value = c.id;
  try {
    const updated = await commentsApi.unhideAdminComment(c.id);
    const idx = comments.value.findIndex((x) => x.id === c.id);
    if (idx !== -1) comments.value[idx] = updated;
  } finally {
    actionLoadingId.value = null;
  }
}

function openDeleteDialog(c: AdminPostCommentItem) {
  deleteDialog.value = { open: true, comment: c };
}

function closeDeleteDialog() {
  deleteDialog.value = { open: false, comment: null };
}

const deleteSubmitting = ref(false);
async function confirmDelete() {
  const c = deleteDialog.value.comment;
  if (!c || !canDelete.value) {
    closeDeleteDialog();
    return;
  }
  deleteSubmitting.value = true;
  try {
    await commentsApi.deleteAdminComment(c.id);
    comments.value = comments.value.filter((x) => x.id !== c.id);
    if (meta.value) meta.value.total = Math.max(0, meta.value.total - 1);
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
    <h1 class="text-xl font-semibold">Модерация комментариев</h1>
    <p class="text-sm text-muted-foreground">
      Все комментарии к постам в одном списке. Введите название поста, чтобы оставить только его комментарии.
    </p>

    <div class="relative space-y-2">
      <Label for="post-search">Пост</Label>
      <div class="relative">
        <Input
          id="post-search"
          v-model="postSearchQuery"
          type="text"
          placeholder="Введите название поста для фильтра…"
          class="w-full"
          autocomplete="off"
        />
        <ul
          v-if="(suggestions.length > 0 || suggestionsLoading) && postSearchQuery.trim()"
          class="absolute left-0 right-0 top-full z-10 mt-1 max-h-60 overflow-auto rounded-md border border-border bg-popover py-1 shadow-md"
        >
          <li v-if="suggestionsLoading" class="px-3 py-2 text-sm text-muted-foreground">
            Загрузка…
          </li>
          <li
            v-for="post in suggestions"
            v-else
            key="post.id"
            class="cursor-pointer px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground"
            @click="selectPost(post)"
          >
            <span class="font-medium">{{ post.title || 'Без названия' }}</span>
            <span v-if="post.guild_name" class="text-muted-foreground"> — {{ post.guild_name }}</span>
          </li>
        </ul>
      </div>
      <div v-if="selectedPost" class="flex items-center gap-2">
        <Badge variant="secondary" class="text-xs">
          {{ selectedPost.title || 'Без названия' }}
          <span v-if="selectedPost.guild_name"> ({{ selectedPost.guild_name }})</span>
        </Badge>
        <Button variant="ghost" size="sm" class="h-6 px-1 text-xs" @click="clearPostFilter">
          Сбросить фильтр
        </Button>
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
            </div>
            <button
              v-if="c.post_title || c.guild_name"
              type="button"
              class="mt-1 text-left text-xs text-muted-foreground hover:text-foreground hover:underline"
              @click="goToPost(c)"
            >
              <template v-if="c.guild_name">
                {{ c.guild_name }} — {{ c.post_title || `Пост #${c.post_id}` }}
              </template>
              <template v-else>
                Пост: {{ c.post_title || `#${c.post_id}` }}
              </template>
            </button>
            <p class="mt-2 whitespace-pre-wrap break-words text-sm">
              {{ c.body }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-1 pt-2 border-t border-border">
          <Button
            v-if="canHide && c.is_hidden"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 text-muted-foreground hover:text-foreground"
            :disabled="actionLoadingId !== null"
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
            :disabled="actionLoadingId !== null"
            title="Скрыть комментарий"
            @click="hideComment(c)"
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
            :disabled="actionLoadingId !== null"
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
      :open="deleteDialog.open"
      title="Удалить комментарий"
      confirm-label="Удалить"
      confirm-variant="destructive"
      :loading="deleteSubmitting"
      @confirm="confirmDelete"
      @update:open="(v) => { if (!v) closeDeleteDialog(); }"
    >
      <template #description>
        <p>Комментарий будет удалён без возможности восстановления.</p>
      </template>
    </ConfirmDialog>
  </div>
</template>
