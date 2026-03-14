<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import { Avatar, Button, ConfirmDialog } from '@/shared/ui';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import type { PostComment } from '@/shared/api/commentsApi';

const props = defineProps<{
  comment: PostComment;
  canComment: boolean;
  canReply: boolean;
  currentUserId: number | null;
  replyToId: number | null;
  replyBody: string;
  replySubmitting: boolean;
  depth?: number;
  highlightCommentId?: number | null;
}>();

const emit = defineEmits<{
  (e: 'update:replyBody', v: string): void;
  (e: 'reply', comment: PostComment): void;
  (e: 'cancelReply'): void;
  (e: 'submitReply'): void;
  (e: 'update', commentId: number, body: string): void;
  (e: 'delete', commentId: number): void;
}>();

const isOwn = computed(() => props.currentUserId != null && props.comment.user_id === props.currentUserId);

const isEditing = ref(false);
const editBody = ref('');
const editSubmitting = ref(false);
const deleteDialogOpen = ref(false);
const deleteSubmitting = ref(false);

const editTextareaRef = ref<HTMLTextAreaElement | null>(null);

function startEdit() {
  editBody.value = props.comment.body;
  isEditing.value = true;
  nextTick(() => {
    editTextareaRef.value?.focus();
    if (editTextareaRef.value) {
      editTextareaRef.value.style.height = '0';
      editTextareaRef.value.style.height = `${Math.min(editTextareaRef.value.scrollHeight, 150)}px`;
    }
  });
}

function cancelEdit() {
  isEditing.value = false;
}

async function saveEdit() {
  const trimmed = editBody.value.trim();
  if (trimmed === props.comment.body || !trimmed) {
    cancelEdit();
    return;
  }
  editSubmitting.value = true;
  try {
    emit('update', props.comment.id, trimmed);
    cancelEdit();
  } finally {
    editSubmitting.value = false;
  }
}

function openDeleteDialog() {
  deleteDialogOpen.value = true;
}

function confirmDelete() {
  deleteSubmitting.value = true;
  emit('delete', props.comment.id);
  deleteDialogOpen.value = false;
  deleteSubmitting.value = false;
}

const depth = computed(() => props.depth ?? props.comment.depth);
const showReplyForm = computed(() => props.replyToId === props.comment.id);
const hasChildren = computed(() => (props.comment.children?.length ?? 0) > 0);
const childrenCollapsed = ref(false);

function toggleChildren() {
  childrenCollapsed.value = !childrenCollapsed.value;
}

const isHighlighted = computed(() => props.highlightCommentId === props.comment.id);

const indentClass = computed(() => {
  const d = depth.value;
  if (d === 0) return '';
  if (d === 1) return 'ml-6 md:ml-8 pl-3 border-l-2 border-muted';
  return 'ml-10 md:ml-12 pl-3 border-l-2 border-muted/70';
});

const avatarFallback = computed(() =>
  (props.comment.author_name || '??').trim().slice(0, 2).toUpperCase()
);

const replyTextareaRef = ref<HTMLTextAreaElement | null>(null);

function autoResizeTextarea(el: EventTarget | null) {
  const ta = el as HTMLTextAreaElement | null;
  if (!ta) return;
  ta.style.height = '0';
  ta.style.height = `${Math.min(ta.scrollHeight, 150)}px`;
}

function onReplyInput(e: Event) {
  const ta = e.target as HTMLTextAreaElement;
  emit('update:replyBody', ta.value);
  autoResizeTextarea(ta);
}

watch(showReplyForm, (visible) => {
  if (visible) {
    nextTick(() => replyTextareaRef.value?.focus());
  }
});
</script>

<style scoped>
.comment-item--highlight {
  animation: comment-highlight 2s ease-out;
}

@keyframes comment-highlight {
  0% { background-color: rgb(34 197 94 / 0.22); }
  50% { background-color: rgb(34 197 94 / 0.08); }
  100% { background-color: transparent; }
}
</style>

<template>
  <div
    :id="`comment-${comment.id}`"
    :class="['comment-item py-4', indentClass, { 'comment-item--highlight': isHighlighted }]"
  >
    <div class="flex gap-3">
      <div class="flex flex-col items-center gap-1 shrink-0">
        <Avatar
          class="h-8 w-8"
          :src="comment.author_avatar_url ?? undefined"
          :alt="comment.author_name"
          :fallback="avatarFallback"
        />
        <button
          v-if="hasChildren"
          type="button"
          class="flex cursor-pointer rounded p-0.5 text-green-600/60 transition-colors hover:bg-muted hover:text-green-600/80"
          :title="childrenCollapsed ? 'Развернуть ответы' : 'Свернуть ответы'"
          :aria-label="childrenCollapsed ? 'Развернуть ответы' : 'Свернуть ответы'"
          @click="toggleChildren"
        >
          <svg
            v-if="childrenCollapsed"
            class="size-4"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <line x1="12" x2="12" y1="5" y2="19" />
            <line x1="5" x2="19" y1="12" y2="12" />
          </svg>
          <svg
            v-else
            class="size-4"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <line x1="5" x2="19" y1="12" y2="12" />
          </svg>
        </button>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-2">
          <span class="font-medium text-foreground">{{ comment.author_name }}</span>
          <span
            v-if="comment.replied_to_author_name"
            class="text-xs text-muted-foreground"
          >
            Ответ для {{ comment.replied_to_author_name }}
          </span>
          <span class="text-xs text-muted-foreground">
            {{ formatRelativeTime(comment.created_at) }}
          </span>
        </div>
        <template v-if="isEditing">
          <textarea
            ref="editTextareaRef"
            v-model="editBody"
            class="mt-1 flex min-h-[2.5rem] w-full resize-none overflow-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            rows="1"
            @input="(e) => { const ta = e.target as HTMLTextAreaElement; ta.style.height = '0'; ta.style.height = `${Math.min(ta.scrollHeight, 150)}px`; }"
          />
          <div class="mt-2 flex gap-2">
            <Button size="sm" :disabled="editSubmitting" @click="saveEdit">
              {{ editSubmitting ? 'Сохранение…' : 'Сохранить' }}
            </Button>
            <Button variant="outline" size="sm" :disabled="editSubmitting" @click="cancelEdit">
              Отмена
            </Button>
          </div>
        </template>
        <template v-else>
          <p class="mt-1 whitespace-pre-wrap break-words text-sm">
            {{ comment.body }}
          </p>
          <div v-if="(canComment && canReply) || isOwn" class="mt-1 flex flex-wrap items-center gap-1">
            <Button
              v-if="canComment && canReply"
              variant="link"
              size="sm"
              class="h-auto shrink-0 p-0 text-xs text-muted-foreground hover:text-foreground"
              @click="emit('reply', comment)"
            >
              Ответить
            </Button>
            <Button
              v-if="isOwn"
              variant="ghost"
              size="icon"
              class="h-6 w-6 shrink-0 text-muted-foreground hover:text-foreground"
              title="Редактировать"
              @click="startEdit"
            >
              <svg class="size-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                <path d="m15 5 4 4" />
              </svg>
            </Button>
            <Button
              v-if="isOwn"
              variant="ghost"
              size="icon"
              class="h-6 w-6 shrink-0 text-muted-foreground hover:text-destructive"
              title="Удалить"
              @click="openDeleteDialog"
            >
              <svg class="size-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18" />
                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                <line x1="10" x2="10" y1="11" y2="17" />
                <line x1="14" x2="14" y1="11" y2="17" />
              </svg>
            </Button>
          </div>
        </template>

        <div v-if="showReplyForm" class="mt-3 space-y-2">
          <textarea
            ref="replyTextareaRef"
            :value="replyBody"
            class="flex min-h-[2.5rem] w-full resize-none overflow-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            :placeholder="`Ответ для ${comment.author_name}…`"
            rows="1"
            @input="onReplyInput"
          />
          <div class="flex gap-2">
            <Button size="sm" :disabled="replySubmitting" @click="emit('submitReply')">
              {{ replySubmitting ? 'Отправка…' : 'Отправить' }}
            </Button>
            <Button variant="outline" size="sm" :disabled="replySubmitting" @click="emit('cancelReply')">
              Отмена
            </Button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="hasChildren"
      class="grid transition-[grid-template-rows] duration-200 ease-in-out"
      :class="childrenCollapsed ? 'grid-rows-[0fr]' : 'grid-rows-[1fr]'"
    >
      <ul class="mt-2 min-h-0 overflow-hidden space-y-0">
        <li
          v-for="child in comment.children"
          :key="child.id"
          class="border-border/30 border-t pt-2 first:border-t-0 first:pt-0"
        >
          <PostCommentItem
          :comment="child"
          :can-comment="canComment"
          :can-reply="true"
          :current-user-id="currentUserId"
          :reply-to-id="replyToId"
          :reply-body="replyBody"
          :reply-submitting="replySubmitting"
          :highlight-comment-id="props.highlightCommentId"
          :depth="depth + 1"
          @update:reply-body="emit('update:replyBody', $event)"
          @reply="emit('reply', $event)"
          @cancel-reply="emit('cancelReply')"
          @submit-reply="emit('submitReply')"
          @update="(id, body) => emit('update', id, body)"
          @delete="(id) => emit('delete', id)"
          />
        </li>
      </ul>
    </div>

    <ConfirmDialog
      :open="deleteDialogOpen"
      title="Удалить комментарий"
      confirm-label="Удалить"
      confirm-variant="destructive"
      :loading="deleteSubmitting"
      @confirm="confirmDelete"
      @update:open="(v) => { deleteDialogOpen = v; }"
    >
      <template #description>
        <p>Вы уверены, что хотите удалить этот комментарий? Отменить действие будет нельзя.</p>
      </template>
    </ConfirmDialog>
  </div>
</template>
