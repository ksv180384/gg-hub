<script setup lang="ts">
import { ref, computed } from 'vue';
import { RouterLink } from 'vue-router';
import { Badge, RelativeTime, Sheet } from '@/shared/ui';
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

function onScroll(e: Event) {
  const el = e.target as HTMLElement;
  if (!el || !props.hasMore || props.loadingMore || props.loading) return;
  const threshold = 80;
  if (el.scrollHeight - el.scrollTop - el.clientHeight < threshold) {
    emit('load-more');
  }
}
</script>

<template>
  <Sheet v-model:open="open" side="right" class="w-full max-w-sm">
    <template #trigger>
      <button
        type="button"
        class="relative flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
        aria-label="Оповещения"
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
        <Badge
          v-if="badgeText"
          variant="destructive"
          class="absolute -right-1 -top-1 flex max-w-[10px] items-center justify-center bg-red-50 text-[10px] text-red-700 hover:text-red-200 dark:bg-red-950 dark:text-red-300"
        >
          {{ badgeText }}
        </Badge>
      </button>
    </template>
    <template #title>Оповещения</template>
    <div class="flex min-h-0 flex-1 flex-col">
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
            class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
            :class="[
              { 'bg-muted/50': expandedId === n.id },
              !n.read_at && 'bg-primary/10',
            ]"
            @click="emit('notification-click', n)"
            @keydown.enter.prevent="emit('notification-click', n)"
            @keydown.space.prevent="emit('notification-click', n)"
            @mouseenter="emit('notification-mouse-enter', n)"
          >
            <div class="min-w-0 flex-1">
              <span class="block break-words">
                {{ expandedId === n.id ? n.message : truncateMessage(n.message, 60) }}
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
                v-if="n.link"
                :to="n.link"
                class="mt-1.5 inline-block text-xs font-medium text-primary underline hover:no-underline"
                @click="emit('update:open', false)"
              >
                {{ getNotificationLinkText(n.link) }}
              </RouterLink>
            </div>
            <button
              type="button"
              class="shrink-0 rounded p-1 opacity-70 hover:opacity-100 hover:bg-destructive/20 disabled:pointer-events-none"
              aria-label="Удалить"
              :disabled="deletingId === n.id"
              @click.stop="emit('delete', n.id)"
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
    </div>
  </Sheet>
</template>
