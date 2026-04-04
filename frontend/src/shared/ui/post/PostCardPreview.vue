<script setup lang="ts">
import { Avatar } from '@/shared/ui';
import type { Post } from '@/shared/api/postsApi';
import { computed, ref } from 'vue';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import { useVideoPlaybackTracking } from '@/shared/lib/useVideoPlaybackTracking';

interface Props {
  post: Post;
  /** Какую дату считать датой публикации: guild | global */
  dateType?: 'guild' | 'global';
  /** ID гильдии — для засчёта просмотра при воспроизведении видео. */
  guildId?: number | null;
  /** ID пользователя автора — если задан, имя автора становится кликабельным (emit authorClick). */
  authorUserId?: number | null;
  /** Показывать название игры (только для админки). */
  showGame?: boolean;
  /** Показывать статусы поста (общий и гильдейский). */
  showStatus?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  dateType: 'guild',
});

function displayBody(): string {
  if (props.post.preview?.trim()) return props.post.preview;
  return '';
}

const isPreviewHtml = computed(
  () => (props.post.preview ?? '').trim().startsWith('<')
);

const emit = defineEmits<{
  (e: 'titleClick'): void;
  (e: 'authorClick', userId: number): void;
  (e: 'commentsClick'): void;
  (e: 'viewRecorded'): void;
}>();

const isAuthorClickable = computed(() => (props.authorUserId ?? 0) > 0);

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

const previewContainerRef = ref<HTMLElement | null>(null);

useVideoPlaybackTracking(previewContainerRef, {
  guildId: computed(() => props.guildId ?? 0),
  postId: computed(() => props.post.id),
  onRecorded: () => emit('viewRecorded'),
});
</script>

<template>
  <article class="bg-card p-4">
    <header class="mb-2 flex items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-3">
        <Avatar
          class="h-20 w-16"
          :src="avatarUrl || undefined"
          :alt="displayName"
          :fallback="avatarFallback"
        />
        <div class="min-w-0">
          <p
            class="truncate text-sm font-medium text-gray-600"
            :class="{ 'cursor-pointer hover:underline': isAuthorClickable }"
            @click.stop="isAuthorClickable && props.authorUserId && emit('authorClick', props.authorUserId)"
          >
            {{ displayName }}
          </p>
          <h3
            class="truncate text-xl font-bold text-foreground/90 cursor-pointer hover:underline"
            :title="post.title || 'Без заголовка'"
            @click.stop="emit('titleClick')"
          >
            {{ post.title || 'Без заголовка' }}
          </h3>
          <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <span class="text-xs text-muted-foreground">{{ displayTime }}</span>
            <span
              v-if="showGame && post.game_name"
              class="text-xs text-muted-foreground"
            >
              {{ post.game_name }}
            </span>
            <template v-if="showStatus">
              <span
                v-if="post.status_global"
                class="text-xs text-muted-foreground"
                title="Статус в общем журнале"
              >
                общие: {{ post.status_global_label ?? post.status_global }}
              </span>
              <span
                v-if="post.status_guild"
                class="text-xs text-muted-foreground"
                title="Статус в гильдии"
              >
                гильдия: {{ post.status_guild_label ?? post.status_guild }}
              </span>
            </template>
          </div>
        </div>
      </div>
    </header>
    <div
      v-if="isPreviewHtml"
      ref="previewContainerRef"
      class="prose prose-sm max-w-none text-md dark:prose-invert [&_p]:my-1 [&_p]:first:mt-0 [&_p]:last:mb-0 [&_a]:text-blue-600 [&_a]:underline [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6"
      v-html="displayBody()"
    />
    <p v-else class="text-sm text-muted-foreground line-clamp-2">
      {{ displayBody() }}
    </p>
    <div
      v-if="post.views_count != null || post.comments_count != null"
      class="mt-3 flex items-center gap-4 border-t pt-3 text-xs text-muted-foreground"
    >
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
      <button
        v-if="post.comments_count != null"
        type="button"
        class="flex cursor-pointer items-center gap-1.5 hover:text-foreground"
        title="Комментарии"
        @click.stop="emit('commentsClick')"
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
        <span>{{ post.comments_count }}</span>
      </button>
    </div>
  </article>
</template>
