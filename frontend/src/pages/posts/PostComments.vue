<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { Button, Select, Separator } from '@/shared/ui';
import PostCommentItem from '@/pages/guilds/[id]/posts/PostCommentItem.vue';
import { commentsApi, type PostComment } from '@/shared/api/commentsApi';
import type { ApiError } from '@/shared/api/errors';

interface CharacterItem {
  id: number;
  name: string;
  avatar_url?: string | null;
}

const props = defineProps<{
  postId: number;
  canComment: boolean;
  myCharacters: CharacterItem[];
}>();

const emit = defineEmits<{
  (e: 'update:commentsCount', count: number): void;
}>();

const auth = useAuthStore();
const currentUserId = computed(() => auth.user?.id ?? null);

const STORAGE_KEY = 'global:comment_character';

function getStoredCharacterId(): number | null {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    return v ? parseInt(v, 10) : null;
  } catch {
    return null;
  }
}

function saveCharacterId(id: number): void {
  try {
    localStorage.setItem(STORAGE_KEY, String(id));
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
  const stored = getStoredCharacterId();
  const validStored = stored && chars.some((c) => c.id === stored);
  selectedCharacterId.value = validStored ? stored : chars[0].id;
}

function onCharacterSelect(v: string) {
  const n = parseInt(v, 10);
  if (!Number.isNaN(n)) {
    selectedCharacterId.value = n;
    saveCharacterId(n);
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

async function loadComments() {
  loading.value = true;
  error.value = null;
  try {
    comments.value = await commentsApi.getGlobalPostComments(props.postId);
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

async function submitReply() {
  if (!replyTo.value || !replyBody.value.trim() || !effectiveCharacterId.value) return;
  replySubmitting.value = true;
  error.value = null;
  try {
    const created = await commentsApi.createGlobalPostComment(props.postId, {
      body: replyBody.value.trim(),
      character_id: effectiveCharacterId.value,
      parent_id: replyTo.value.id,
    });
    addCommentToTree(comments.value, created, created.parent_id ?? replyTo.value.id);
    saveCharacterId(effectiveCharacterId.value);
    cancelReply();
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
    const created = await commentsApi.createGlobalPostComment(props.postId, {
      body: rootBody.value.trim(),
      character_id: effectiveCharacterId.value,
    });
    comments.value = [...comments.value, created];
    saveCharacterId(effectiveCharacterId.value);
    rootBody.value = '';
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

function addCommentToTree(list: PostComment[], created: PostComment, parentId: number): boolean {
  for (const c of list) {
    if (c.id === parentId) {
      c.children = c.children ?? [];
      c.children = [...c.children, created];
      return true;
    }
    if (c.children?.length && addCommentToTree(c.children, created, parentId)) return true;
  }
  return false;
}

function updateCommentInTree(list: PostComment[], commentId: number, updates: Partial<PostComment>): boolean {
  for (const c of list) {
    if (c.id === commentId) {
      Object.assign(c, updates);
      return true;
    }
    if (c.children?.length && updateCommentInTree(c.children, commentId, updates)) return true;
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
    if (c.children?.length && removeCommentFromTree(c.children, commentId)) return true;
  }
  return false;
}

async function onUpdateComment(commentId: number, body: string) {
  error.value = null;
  try {
    const updated = await commentsApi.updateGlobalPostComment(props.postId, commentId, { body });
    updateCommentInTree(comments.value, commentId, { body: updated.body });
  } catch (e) {
    error.value = (e as ApiError)?.message ?? 'Не удалось сохранить';
  }
}

async function onDeleteComment(commentId: number) {
  error.value = null;
  try {
    await commentsApi.deleteGlobalPostComment(props.postId, commentId);
    removeCommentFromTree(comments.value, commentId);
  } catch (e) {
    error.value = (e as ApiError)?.message ?? 'Не удалось удалить';
  }
}

onMounted(() => {
  initSelectedCharacter();
  loadComments();
});

watch(() => props.myCharacters, initSelectedCharacter);
</script>

<template>
  <section id="comments" class="space-y-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold">Комментарии</h2>
      <span class="text-xs text-muted-foreground">{{ totalCount }}</span>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Загрузка комментариев…</p>
    <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

    <div v-if="props.canComment" class="space-y-3 rounded-md border border-border bg-muted/20 p-3">
      <div v-if="props.myCharacters.length" class="flex items-center gap-2">
        <span class="text-xs text-muted-foreground shrink-0">От имени</span>
        <Select
          :model-value="effectiveCharacterId ? String(effectiveCharacterId) : undefined"
          :options="props.myCharacters.map((c) => ({ value: String(c.id), label: c.name }))"
          placeholder="Выберите персонажа"
          trigger-class="h-8 min-w-[220px] text-xs"
          content-class="z-[100]"
          @update:model-value="onCharacterSelect"
        />
      </div>
      <textarea
        v-model="rootBody"
        class="min-h-[2.5rem] w-full resize-none overflow-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
        placeholder="Напишите комментарий…"
      />
      <div class="flex justify-end">
        <Button size="sm" :disabled="rootSubmitting || !rootBody.trim() || !effectiveCharacterId" @click="submitRoot">
          {{ rootSubmitting ? 'Отправка…' : 'Отправить' }}
        </Button>
      </div>
    </div>

    <Separator />

    <div v-if="!loading" class="space-y-3">
      <PostCommentItem
        v-for="c in comments"
        :key="c.id"
        :comment="c"
        :can-comment="props.canComment"
        :current-user-id="currentUserId"
        :can-reply="props.canComment"
        :reply-to-id="replyTo?.id ?? null"
        :reply-body="replyBody"
        :reply-submitting="replySubmitting"
        @update:reply-body="replyBody = $event"
        @reply="startReply"
        @cancel-reply="cancelReply"
        @submit-reply="submitReply"
        @update="onUpdateComment"
        @delete="onDeleteComment"
      />
    </div>
  </section>
</template>
