<script setup lang="ts">
import { Avatar } from '@/shared/ui';
import type { Post } from '@/shared/api/postsApi';
import { computed } from 'vue';
import { formatRelativeTime } from '@/shared/lib/relativeTime';

interface Props {
  post: Post;
  /** Какую дату считать датой публикации: guild | global */
  dateType?: 'guild' | 'global';
  /** Показывать статус поста (только для пользователей с правом publikovat-post) */
  showStatus?: boolean;
  /** Актуальное количество комментариев (переопределяет post.comments_count) */
  commentsCount?: number | null;
  /** ID пользователя автора — если задан, имя автора становится кликабельным (emit authorClick). */
  authorUserId?: number | null;
  /** Показывать название игры (только для админки). */
  showGame?: boolean;
}

const emit = defineEmits<{
  (e: 'authorClick', userId: number): void;
}>();

const props = withDefaults(defineProps<Props>(), {
  dateType: 'guild',
  showStatus: false,
});

const isHtmlBody = computed(
  () => (props.post.body ?? '').trim().startsWith('<')
);

const displayIso = computed(() => {
  const p = props.post;
  const published =
    props.dateType === 'guild' ? p.published_at_guild : p.published_at_global;
  return published || p.updated_at || p.created_at;
});

const displayTime = computed(() => formatRelativeTime(displayIso.value));
const displayName = computed(
  () => props.post.author_name ?? 'Неизвестный персонаж'
);
const avatarUrl = computed(
  () => props.post.author_avatar_url || null
);
const avatarFallback = computed(() =>
  (displayName.value || '??').trim().slice(0, 2).toUpperCase()
);

const displayStatus = computed(() =>
  props.dateType === 'guild'
    ? (props.post.status_guild_label ?? '—')
    : (props.post.status_global_label ?? '—')
);

const displayCommentsCount = computed(() =>
  props.commentsCount != null ? props.commentsCount : (props.post.comments_count ?? null)
);

const isAuthorClickable = computed(() => (props.authorUserId ?? 0) > 0);
</script>

<template>
  <article
    class="overflow-hidden bg-accent/30 rounded-[calc(var(--radius)-2px)] border shadow-sm"
  >
    <header class="flex items-start justify-between gap-3 p-4 pb-2">
      <div class="flex min-w-0 items-start gap-3">
        <Avatar
          class="h-10 w-10 rounded-full shrink-0"
          :src="avatarUrl || undefined"
          :alt="displayName"
          :fallback="avatarFallback"
        />
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <button
              type="button"
              class="min-w-0 truncate text-sm font-medium text-foreground/80 hover:text-foreground disabled:cursor-default disabled:hover:no-underline"
              :class="{ 'cursor-pointer hover:underline': isAuthorClickable }"
              :disabled="!isAuthorClickable"
              @click.stop="isAuthorClickable && props.authorUserId && emit('authorClick', props.authorUserId)"
            >
              {{ displayName }}
            </button>
          </div>
          <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <span class="text-xs text-muted-foreground">
              {{ displayTime }}
            </span>
            <span
              v-if="showGame && post.game_name"
              class="text-xs text-muted-foreground"
            >
              {{ post.game_name }}
            </span>
          </div>
        </div>
      </div>
    </header>

    <div class="px-4 pb-2">
      <h3
        class="text-[22px] leading-snug font-semibold text-foreground/95"
        :title="post.title || 'Без заголовка'"
      >
        {{ post.title || 'Без заголовка' }}
      </h3>
    </div>
    <div
      v-if="isHtmlBody"
      class="px-4 pb-4 prose prose-sm max-w-none text-sm dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6"
      v-html="post.body ?? ''"
    />
    <p v-else class="px-4 pb-4 whitespace-pre-wrap text-sm">
      {{ post.body ?? '' }}
    </p>
    <div
      v-if="post.views_count != null || displayCommentsCount != null || (showStatus && (post.status_guild ?? post.status_global))"
      class="flex items-center justify-between gap-3 border-t bg-card px-4 py-3 text-xs text-muted-foreground"
    >
      <div class="flex items-center gap-4">
        <div
          v-if="post.views_count != null"
          class="flex items-center gap-1.5"
          title="Просмотры"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="shrink-0"
          >
            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
          <span>{{ post.views_count }}</span>
        </div>
        <div
          v-if="displayCommentsCount != null"
          class="flex items-center gap-1.5"
          title="Комментарии"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="shrink-0"
          >
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          <span>{{ displayCommentsCount }}</span>
        </div>
      </div>
      <span
        v-if="showStatus && (post.status_guild ?? post.status_global)"
        class="shrink-0"
      >
        {{ displayStatus }}
      </span>
    </div>
  </article>
</template>
