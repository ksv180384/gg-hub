<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { Badge, Button, RelativeTime, Sheet } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import type { NotificationItem } from '@/shared/api/notificationsApi';

function getNotificationLinkText(link: string | null | undefined): string {
  if (!link) return 'Перейти';
  if (link.includes('/posts') || link.includes('/posts/')) return 'Перейти к посту';
  if (link.includes('/applications')) return 'Перейти к заявке';
  return 'Перейти';
}

function truncateMessage(msg: string, max = 60) {
  if (msg.length <= max) return msg;
  return msg.slice(0, max) + '…';
}

interface Props {
  open: boolean;
  notifications: NotificationItem[];
  unreadCount: number;
  loading: boolean;
  loadingMore: boolean;
  hasMore: boolean;
  expandedId: number | null;
  deletingId: number | null;
  bulkDeleting: boolean;
  timezone?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
  timezone: null,
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'load'): void;
  (e: 'load-more'): void;
  (e: 'notification-click', n: NotificationItem): void;
  (e: 'notification-mouse-enter', n: NotificationItem): void;
  (e: 'delete', id: number): void;
  (e: 'delete-many', ids: number[]): void;
}>();

const open = computed({
  get: () => props.open,
  set: (v: boolean) => emit('update:open', v),
});

const notificationsListRef = ref<HTMLElement | null>(null);

const badgeText = computed(() => {
  if (props.unreadCount <= 0) return '';
  if (props.unreadCount > 9) return '9+';
  return String(props.unreadCount);
});

/** Режим выбора: показываем чекбоксы и нижнюю панель с действиями. */
const selectionMode = ref(false);
const selectedIds = ref<Set<number>>(new Set());

function toggleSelected(id: number) {
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function isSelected(id: number): boolean {
  return selectedIds.value.has(id);
}

const selectedCount = computed(() => selectedIds.value.size);

const deleteOneDialogOpen = ref(false);
const pendingDeleteId = ref<number | null>(null);

function requestDeleteOne(id: number) {
  pendingDeleteId.value = id;
  deleteOneDialogOpen.value = true;
}

function confirmDeleteOne() {
  const id = pendingDeleteId.value;
  if (!id) return;
  deleteOneDialogOpen.value = false;
  pendingDeleteId.value = null;
  emit('delete', id);
}

const deleteManyDialogOpen = ref(false);
const pendingDeleteManyIds = ref<number[]>([]);

function requestDeleteMany(ids: number[]) {
  const clean = ids.filter((id) => Number.isFinite(id) && id > 0);
  if (clean.length === 0) return;
  pendingDeleteManyIds.value = clean;
  deleteManyDialogOpen.value = true;
}

function confirmDeleteMany() {
  const ids = pendingDeleteManyIds.value;
  if (!ids.length) return;
  deleteManyDialogOpen.value = false;
  pendingDeleteManyIds.value = [];
  emit('delete-many', ids);
}

const allSelected = computed(
  () =>
    props.notifications.length > 0 &&
    props.notifications.every((n) => selectedIds.value.has(n.id))
);

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set();
    return;
  }
  selectedIds.value = new Set(props.notifications.map((n) => n.id));
}

function enterSelectionMode(initialId?: number) {
  selectionMode.value = true;
  if (initialId) {
    const next = new Set(selectedIds.value);
    next.add(initialId);
    selectedIds.value = next;
  }
}

function exitSelectionMode() {
  selectionMode.value = false;
  selectedIds.value = new Set();
}

function onConfirmBulkDelete() {
  if (selectedCount.value === 0) return;
  requestDeleteMany(Array.from(selectedIds.value));
}

watch(
  () => props.notifications,
  (items) => {
    if (selectedIds.value.size === 0) return;
    const existing = new Set(items.map((n) => n.id));
    const filtered = new Set<number>();
    selectedIds.value.forEach((id) => {
      if (existing.has(id)) filtered.add(id);
    });
    if (filtered.size !== selectedIds.value.size) {
      selectedIds.value = filtered;
    }
    if (selectionMode.value && filtered.size === 0 && items.length === 0) {
      selectionMode.value = false;
    }
  }
);

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) exitSelectionMode();
  }
);

function onScroll(e: Event) {
  const el = e.target as HTMLElement;
  if (!el || !props.hasMore || props.loadingMore || props.loading) return;
  const threshold = 80;
  if (el.scrollHeight - el.scrollTop - el.clientHeight < threshold) {
    emit('load-more');
  }
}

function onItemClick(n: NotificationItem) {
  if (selectionMode.value) {
    toggleSelected(n.id);
    return;
  }
  emit('notification-click', n);
}

function onItemMouseEnter(n: NotificationItem) {
  if (selectionMode.value) return;
  emit('notification-mouse-enter', n);
}
</script>

<template>
  <Sheet v-model:open="open" side="right" class="w-full max-w-sm">
    <template #trigger>
      <button
        type="button"
        class="relative flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
        aria-label="Оповещения"
        title="Оповещения"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          class="h-[1.125rem] w-[1.125rem]"
        >
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
        <!-- Резервируем место под бейдж, чтобы его появление не давало CLS -->
        <Badge
          variant="destructive"
          class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center bg-red-50 px-1 text-[10px] leading-none text-red-700 dark:bg-red-950 dark:text-red-300"
          :class="badgeText ? 'opacity-100' : 'opacity-0 pointer-events-none'"
          aria-hidden="true"
        >
          {{ badgeText || '0' }}
        </Badge>
      </button>
    </template>
    <template #title>Оповещения</template>
    <div class="flex min-h-0 flex-1 flex-col">
      <div
        v-if="notifications.length > 0 || selectionMode"
        class="flex shrink-0 items-center justify-between gap-2 border-b border-border/60 pb-2 pr-10"
      >
        <template v-if="selectionMode">
          <label
            class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-sm text-muted-foreground"
          >
            <input
              type="checkbox"
              class="h-4 w-4 shrink-0 cursor-pointer accent-primary"
              :checked="allSelected"
              :disabled="notifications.length === 0"
              @change="toggleSelectAll"
            />
            <span class="truncate">
              Выбрано: {{ selectedCount }}
            </span>
          </label>
          <Button
            variant="ghost"
            size="sm"
            class="h-8 px-2 text-xs"
            :disabled="bulkDeleting"
            @click="exitSelectionMode"
          >
            Отмена
          </Button>
        </template>
        <template v-else>
          <Button
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0"
            :disabled="notifications.length === 0"
            aria-label="Выбрать оповещения"
            title="Выбрать оповещения"
            @click="enterSelectionMode()"
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
              class="h-4 w-4"
            >
              <rect x="3" y="3" width="18" height="18" rx="3" />
              <path d="m8 12 3 3 5-6" />
            </svg>
          </Button>
          <span class="truncate text-xs text-muted-foreground">
            Всего: {{ notifications.length }}
          </span>
        </template>
      </div>
      <div
        ref="notificationsListRef"
        class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto pt-2"
        @scroll="onScroll"
      >
        <p v-if="loading" class="px-2 py-4 text-sm text-muted-foreground">
          Загрузка…
        </p>
        <template v-else-if="notifications.length === 0">
          <p class="px-2 py-4 text-sm text-muted-foreground">
            Нет оповещений
          </p>
        </template>
        <template v-else>
          <div
            v-for="n in notifications"
            :key="n.id"
            role="button"
            tabindex="0"
            class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
            :class="[
              { 'bg-muted/50': expandedId === n.id && !selectionMode },
              !n.read_at && 'bg-primary/10',
              selectionMode && isSelected(n.id) && 'bg-accent',
            ]"
            @click="onItemClick(n)"
            @keydown.enter.prevent="onItemClick(n)"
            @keydown.space.prevent="onItemClick(n)"
            @mouseenter="onItemMouseEnter(n)"
          >
            <input
              v-if="selectionMode"
              type="checkbox"
              class="h-4 w-4 shrink-0 cursor-pointer accent-primary"
              :checked="isSelected(n.id)"
              :aria-label="`Выбрать оповещение ${n.id}`"
              @click.stop="toggleSelected(n.id)"
              @change.stop
            />
            <div class="min-w-0 flex-1">
              <span class="block break-words">
                {{ expandedId === n.id && !selectionMode ? n.message : truncateMessage(n.message, 60) }}
              </span>
              <span
                v-if="n.created_at"
                class="mt-1.5 block text-xs text-muted-foreground"
              >
                <RelativeTime
                  :date="n.created_at"
                  :timezone="timezone ?? undefined"
                  tag="time"
                  class="text-xs text-muted-foreground"
                />
              </span>
              <RouterLink
                v-if="n.link && !selectionMode"
                :to="n.link"
                class="mt-1.5 inline-block text-xs font-medium text-primary underline hover:no-underline"
                @click="emit('update:open', false)"
              >
                {{ getNotificationLinkText(n.link) }}
              </RouterLink>
            </div>
            <button
              v-if="!selectionMode"
              type="button"
              class="shrink-0 rounded p-1 opacity-70 hover:opacity-100 hover:bg-destructive/20 disabled:pointer-events-none"
              aria-label="Удалить"
              :disabled="deletingId === n.id"
              @click.stop="requestDeleteOne(n.id)"
            >
              <svg
                v-if="deletingId === n.id"
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                class="h-3.5 w-3.5 animate-spin"
              >
                <path d="M21 12a9 9 0 1 1-6.22-8.56" />
              </svg>
              <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="h-3.5 w-3.5"
              >
                <path d="M3 6h18" />
                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                <line x1="10" x2="10" y1="11" y2="17" />
                <line x1="14" x2="14" y1="11" y2="17" />
              </svg>
            </button>
          </div>
          <div
            v-if="loadingMore"
            class="flex items-center justify-center gap-2 px-2 py-3"
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
              class="h-4 w-4 shrink-0 animate-spin text-muted-foreground"
            >
              <path d="M21 12a9 9 0 1 1-6.22-8.56" />
            </svg>
            <span class="text-sm text-muted-foreground">Загрузка…</span>
          </div>
        </template>
      </div>
      <div
        v-if="selectionMode"
        class="flex shrink-0 items-center justify-between gap-2 border-t border-border/60 pt-3"
      >
        <span class="text-xs text-muted-foreground">
          {{ selectedCount > 0 ? `Удалить ${selectedCount}?` : 'Выберите оповещения' }}
        </span>
        <Button
          variant="destructive"
          size="sm"
          class="h-8 px-3"
          :disabled="selectedCount === 0 || bulkDeleting"
          @click="onConfirmBulkDelete"
        >
          <svg
            v-if="bulkDeleting"
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            class="mr-1.5 h-3.5 w-3.5 animate-spin"
          >
            <path d="M21 12a9 9 0 1 1-6.22-8.56" />
          </svg>
          Удалить выбранные
        </Button>
      </div>
    </div>
  </Sheet>

  <ConfirmDialog
    :open="deleteOneDialogOpen"
    title="Удалить оповещение?"
    description="Это действие нельзя отменить."
    confirm-label="Удалить"
    cancel-label="Отмена"
    :loading="pendingDeleteId != null && deletingId === pendingDeleteId"
    confirm-variant="destructive"
    @update:open="(v) => { deleteOneDialogOpen = v; if (!v) pendingDeleteId = null; }"
    @confirm="confirmDeleteOne"
  />

  <ConfirmDialog
    :open="deleteManyDialogOpen"
    :title="`Удалить выбранные оповещения (${pendingDeleteManyIds.length})?`"
    description="Это действие нельзя отменить."
    confirm-label="Удалить"
    cancel-label="Отмена"
    :loading="bulkDeleting"
    confirm-variant="destructive"
    @update:open="(v) => { deleteManyDialogOpen = v; if (!v) pendingDeleteManyIds = []; }"
    @confirm="confirmDeleteMany"
  />
</template>
