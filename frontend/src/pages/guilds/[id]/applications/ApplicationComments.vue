<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { Button, Select, Separator } from '@/shared/ui';
import PostCommentItem from '@/pages/guilds/[id]/posts/PostCommentItem.vue';
import {
  guildsApi,
  type GuildApplicationCommentCharacter,
  type GuildApplicationCommentItem,
} from '@/shared/api/guildsApi';

const props = defineProps<{
  guildId: number;
  applicationId: number;
}>();

const auth = useAuthStore();
const currentUserId = computed(() => auth.user?.id ?? null);

const comments = ref<GuildApplicationCommentItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const replyTo = ref<GuildApplicationCommentItem | null>(null);
const replyBody = ref('');
const replySubmitting = ref(false);
const rootBody = ref('');
const rootSubmitting = ref(false);
const myCharacters = ref<GuildApplicationCommentCharacter[]>([]);
const selectedCharacterId = ref<number | null>(null);

async function loadComments() {
  loading.value = true;
  error.value = null;
  try {
    const payload = await guildsApi.getGuildApplicationComments(props.guildId, props.applicationId);
    comments.value = payload.comments;
    myCharacters.value = payload.myCharacters ?? [];
    selectedCharacterId.value = payload.defaultCharacterId ?? myCharacters.value[0]?.id ?? null;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить комментарии';
  } finally {
    loading.value = false;
  }
}

function onCharacterSelect(v: string) {
  const n = parseInt(v, 10);
  if (!Number.isNaN(n)) selectedCharacterId.value = n;
}

function startReply(comment: GuildApplicationCommentItem) {
  replyTo.value = comment;
  replyBody.value = '';
}

function cancelReply() {
  replyTo.value = null;
  replyBody.value = '';
}

function addCommentToTree(list: GuildApplicationCommentItem[], newComment: GuildApplicationCommentItem, parentId: number): boolean {
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

function updateCommentInTree(list: GuildApplicationCommentItem[], commentId: number, updates: Partial<GuildApplicationCommentItem>): boolean {
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

function scrollToComment(commentId: number) {
  nextTick(() => {
    const el = document.getElementById(`comment-${commentId}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
}

async function submitRoot() {
  const body = rootBody.value.trim();
  if (!body || !selectedCharacterId.value) return;
  rootSubmitting.value = true;
  error.value = null;
  try {
    const created = await guildsApi.createGuildApplicationComment(
      props.guildId,
      props.applicationId,
      body,
      undefined,
      selectedCharacterId.value
    );
    comments.value = [...comments.value, created];
    rootBody.value = '';
    scrollToComment(created.id);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить комментарий';
  } finally {
    rootSubmitting.value = false;
  }
}

async function submitReply() {
  const parent = replyTo.value;
  const body = replyBody.value.trim();
  if (!parent || !body || !selectedCharacterId.value) return;
  replySubmitting.value = true;
  error.value = null;
  try {
    const created = await guildsApi.createGuildApplicationComment(
      props.guildId,
      props.applicationId,
      body,
      parent.id,
      selectedCharacterId.value
    );
    addCommentToTree(comments.value, created, created.parent_id ?? parent.id);
    cancelReply();
    scrollToComment(created.id);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить комментарий';
  } finally {
    replySubmitting.value = false;
  }
}

async function onUpdateComment(commentId: number, body: string) {
  error.value = null;
  try {
    const updated = await guildsApi.updateGuildApplicationComment(props.guildId, props.applicationId, commentId, body);
    updateCommentInTree(comments.value, commentId, { body: updated.body });
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось сохранить комментарий';
  }
}

async function onDeleteComment(commentId: number) {
  error.value = null;
  try {
    await guildsApi.deleteGuildApplicationComment(props.guildId, props.applicationId, commentId);
    await loadComments();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось удалить комментарий';
  }
}

onMounted(loadComments);
</script>

<template>
  <div class="space-y-4 border-t border-border pt-4">
    <h3 class="text-sm font-medium text-muted-foreground">Комментарии</h3>

    <div class="space-y-2">
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
        class="flex min-h-[2.5rem] w-full resize-none overflow-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        placeholder="Написать комментарий…"
        rows="1"
      />
      <Button size="sm" :disabled="!rootBody.trim() || rootSubmitting || !selectedCharacterId" @click="submitRoot">
        {{ rootSubmitting ? 'Отправка…' : 'Отправить' }}
      </Button>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Загрузка комментариев…</p>
    <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

    <template v-else>
      <Separator class="my-4" />
      <ul v-if="comments.length" class="space-y-0">
        <li v-for="comment in comments" :key="comment.id">
          <PostCommentItem
            :comment="comment"
            :can-comment="true"
            :can-reply="true"
            :current-user-id="currentUserId"
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
        </li>
      </ul>
      <p v-else class="py-6 text-center text-sm text-muted-foreground">Пока нет комментариев.</p>
    </template>
  </div>
</template>
