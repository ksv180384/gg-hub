<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { Avatar, Button, Select, Separator } from '@/shared/ui';
import PostCommentItem from './PostCommentItem.vue';
import { commentsApi, type PostComment } from '@/shared/api/commentsApi';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import type { ApiError } from '@/shared/api/errors';

interface GuildCharacter {
  id: number;
  name: string;
  avatar_url?: string | null;
}

const props = defineProps<{
  guildId: number;
  postId: number;
  canComment: boolean;
  myCharacters: GuildCharacter[];
}>();

const emit = defineEmits<{
  (e: 'update:commentsCount', count: number): void;
}>();

const route = useRoute();
const auth = useAuthStore();
const currentUserId = computed(() => auth.user?.id ?? null);

const STORAGE_KEY_PREFIX = 'guild:comment_character:';

function getStoredCharacterId(guildId: number): number | null {
  try {
    const v = localStorage.getItem(STORAGE_KEY_PREFIX + guildId);
    return v ? parseInt(v, 10) : null;
  } catch {
    return null;
  }
}

function saveCharacterId(guildId: number, id: number): void {
  try {
    localStorage.setItem(STORAGE_KEY_PREFIX + guildId, String(id));
  } catch {
    /* ignore */
  }
}

const selectedCharacterId = ref<number | null>(null);

function initSelectedCharacter() {
  const chars = props.myCharacters;
  if (!chars.length) {
    selectedCharacterId.value = null;
    return;
  }
  const stored = getStoredCharacterId(props.guildId);
  const validStored = stored && chars.some((c) => c.id === stored);
  selectedCharacterId.value = validStored ? stored : chars[0].id;
}

function onCharacterSelect(v: string) {
  const n = parseInt(v, 10);
  if (!Number.isNaN(n)) {
    selectedCharacterId.value = n;
    saveCharacterId(props.guildId, n);
  }
}

const effectiveCharacterId = computed(() => {
  const id = selectedCharacterId.value;
  const chars = props.myCharacters;
  if (id && chars.some((c) => c.id === id)) return id;
  return chars[0]?.id ?? null;
});

const comments = ref<PostComment[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const replyTo = ref<PostComment | null>(null);
const replyBody = ref('');
const replySubmitting = ref(false);
const rootBody = ref('');
const rootSubmitting = ref(false);
const highlightedCommentId = ref<number | null>(null);

async function loadComments() {
  loading.value = true;
  error.value = null;
  try {
    comments.value = await commentsApi.getGuildPostComments(props.guildId, props.postId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить комментарии';
  } finally {
    loading.value = false;
  }
}

function cancelReply() {
  replyTo.value = null;
  replyBody.value = '';
}

function startReply(comment: PostComment) {
  replyTo.value = comment;
  replyBody.value = '';
}

const canReplyTo = (_c: PostComment) => true;

async function submitReply() {
  if (!replyTo.value || !replyBody.value.trim() || !effectiveCharacterId.value) return;
  replySubmitting.value = true;
  error.value = null;
  try {
    const created = await commentsApi.createGuildPostComment(props.guildId, props.postId, {
      body: replyBody.value.trim(),
      character_id: effectiveCharacterId.value,
      parent_id: replyTo.value.id,
    });
    addCommentToTree(comments.value, created, created.parent_id ?? replyTo.value.id);
    saveCharacterId(props.guildId, effectiveCharacterId.value);
    cancelReply();
    scrollToComment(created.id);
  } catch (e) {
    const apiErr = e as ApiError;
    error.value = apiErr?.message ?? 'Не удалось отправить комментарий';
  } finally {
    replySubmitting.value = false;
  }
}

async function submitRoot() {
  if (!rootBody.value.trim() || !effectiveCharacterId.value) return;
  rootSubmitting.value = true;
  error.value = null;
  try {
    const created = await commentsApi.createGuildPostComment(props.guildId, props.postId, {
      body: rootBody.value.trim(),
      character_id: effectiveCharacterId.value,
    });
    comments.value = [...comments.value, created];
    saveCharacterId(props.guildId, effectiveCharacterId.value);
    rootBody.value = '';
    scrollToComment(created.id);
  } catch (e) {
    const apiErr = e as ApiError;
    error.value = apiErr?.message ?? 'Не удалось отправить комментарий';
  } finally {
    rootSubmitting.value = false;
  }
}

const totalCount = computed(() => {
  function count(cs: PostComment[]): number {
    return cs.reduce((acc, c) => acc + 1 + count(c.children ?? []), 0);
  }
  return count(comments.value);
});

watch([totalCount, loading], () => {
  if (!loading.value) emit('update:commentsCount', totalCount.value);
}, { immediate: true });

function autoResizeTextarea(el: EventTarget | null) {
  const ta = el as HTMLTextAreaElement | null;
  if (!ta) return;
  ta.style.height = '0';
  ta.style.height = `${Math.min(ta.scrollHeight, 200)}px`;
}

function updateCommentInTree(list: PostComment[], commentId: number, updates: Partial<PostComment>): boolean {
  for (const c of list) {
    if (c.id === commentId) {
      Object.assign(c, updates);
      return true;
    }
    if (c.children?.length && updateCommentInTree(c.children, commentId, updates)) {
      return true;
    }
  }
  return false;
}

function removeCommentFromTree(list: PostComment[], commentId: number): boolean {
  const idx = list.findIndex((c) => c.id === commentId);
  if (idx >= 0) {
    list.splice(idx, 1);
    return true;
  }
  for (const c of list) {
    if (c.children?.length && removeCommentFromTree(c.children, commentId)) {
      return true;
    }
  }
  return false;
}

async function onUpdateComment(commentId: number, body: string) {
  error.value = null;
  try {
    const updated = await commentsApi.updateGuildPostComment(props.guildId, props.postId, commentId, { body });
    updateCommentInTree(comments.value, commentId, { body: updated.body });
  } catch (e) {
    error.value = (e as ApiError)?.message ?? 'Не удалось сохранить';
  }
}

async function onDeleteComment(commentId: number) {
  error.value = null;
  try {
    await commentsApi.deleteGuildPostComment(props.guildId, props.postId, commentId);
    removeCommentFromTree(comments.value, commentId);
  } catch (e) {
    error.value = (e as ApiError)?.message ?? 'Не удалось удалить';
  }
}

function addCommentToTree(list: PostComment[], newComment: PostComment, parentId: number): boolean {
  for (const c of list) {
    if (c.id === parentId) {
      c.children = [...(c.children ?? []), newComment];
      return true;
    }
    if (c.children?.length && addCommentToTree(c.children, newComment, parentId)) {
      return true;
    }
  }
  return false;
}

function scrollToComment(commentId: number) {
  nextTick(() => {
    const el = document.getElementById(`comment-${commentId}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightedCommentId.value = commentId;
    setTimeout(() => { highlightedCommentId.value = null; }, 2000);
  });
}

function scrollToCommentsBlock() {
  nextTick(() => {
    const el = document.getElementById('comments');
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

function scrollToCommentFromHash() {
  const hash = route.hash;
  if (hash === '#comments') {
    scrollToCommentsBlock();
    return;
  }
  const m = hash?.match(/^#comment-(\d+)$/);
  if (!m?.[1]) return;
  scrollToComment(Number(m[1]));
}

watch(() => [props.guildId, props.postId] as const, () => {
  loadComments();
}, { immediate: false });

watch(() => props.myCharacters, () => {
  initSelectedCharacter();
}, { immediate: true });

watch([() => route.hash, loading], () => {
  if (!loading.value) scrollToCommentFromHash();
}, { immediate: true });

onMounted(() => {
  initSelectedCharacter();
  loadComments();
});
</script>

<template>
  <div id="comments" class="space-y-4">
    <h2 class="text-lg font-semibold tracking-tight">
      Комментарии
      <span v-if="totalCount" class="ml-1.5 text-muted-foreground">
        ({{ totalCount }})
      </span>
    </h2>

    <p v-if="loading" class="text-sm text-muted-foreground">
      Загрузка комментариев…
    </p>
    <p v-else-if="error" class="text-sm text-destructive">
      {{ error }}
    </p>

    <template v-else>
      <div v-if="canComment" class="space-y-2">
        <div v-if="myCharacters.length > 1" class="mb-2">
          <label class="mb-1 block text-xs font-medium text-muted-foreground">От имени персонажа</label>
          <Select
            :model-value="selectedCharacterId != null ? String(selectedCharacterId) : ''"
            :options="myCharacters.map((c) => ({ value: String(c.id), label: c.name }))"
            placeholder="Выберите персонажа"
            trigger-class="w-full max-w-xs"
            @update:model-value="onCharacterSelect"
          />
        </div>
        <textarea
          v-model="rootBody"
          class="flex min-h-[2.5rem] w-full resize-none overflow-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          placeholder="Написать комментарий…"
          rows="1"
          @input="autoResizeTextarea($event.target)"
        />
        <Button
          size="sm"
          :disabled="!rootBody.trim() || rootSubmitting"
          @click="submitRoot"
        >
          {{ rootSubmitting ? 'Отправка…' : 'Отправить' }}
        </Button>
      </div>

      <Separator class="my-4" />

      <ul v-if="comments.length" class="space-y-0">
        <li
          v-for="comment in comments"
          :key="comment.id"
          class="border-b border-border/50 last:border-b-0"
        >
          <PostCommentItem
            :comment="comment"
            :can-comment="canComment"
            :can-reply="canReplyTo(comment)"
            :current-user-id="currentUserId"
            :reply-to-id="replyTo?.id ?? null"
            :reply-body="replyBody"
            :reply-submitting="replySubmitting"
            :highlight-comment-id="highlightedCommentId"
            @update:reply-body="replyBody = $event"
            @reply="startReply"
            @cancel-reply="cancelReply"
            @submit-reply="submitReply"
            @update="onUpdateComment"
            @delete="onDeleteComment"
          />
        </li>
      </ul>
      <p v-else class="py-6 text-center text-sm text-muted-foreground">
        Пока нет комментариев.
      </p>
    </template>
  </div>
</template>
