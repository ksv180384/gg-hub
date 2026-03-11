<script setup lang="ts">
import { Avatar } from '@/shared/ui';
import type { Post } from '@/shared/api/postsApi';
import { computed, ref } from 'vue';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import { useVideoPlaybackTracking } from '@/shared/lib/useVideoPlaybackTracking';

interface Props {
  post: Post;
  /**
   * Какую дату считать датой публикации:
   * - guild  — published_at_guild → updated_at → created_at
   * - global — published_at_global → updated_at → created_at
   */
  dateType?: 'guild' | 'global';
  /** Показывать превью (первые 20 слов) вместо полного текста. Для списков. */
  showPreview?: boolean;
  /** ID гильдии — для засчёта просмотра при воспроизведении видео в превью. */
  guildId?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
  dateType: 'guild',
  showPreview: false,
});

function displayBody(preview: boolean): string {
  if (!preview) return props.post.body ?? '';
  if (props.post.preview?.trim()) return props.post.preview;
  const text = (props.post.body ?? '').replace(/<[^>]+>/g, '').trim();
  return text.length > 160 ? text.slice(0, 160) + '…' : text;
}

const isPreviewHtml = computed(
  () => props.showPreview && (props.post.preview ?? '').trim().startsWith('<')
);
const isHtmlBody = computed(
  () => !props.showPreview && (props.post.body ?? '').trim().startsWith('<')
);

const emit = defineEmits<{
  (e: 'titleClick'): void;
  (e: 'viewRecorded'): void;
}>();

const displayIso = computed(() => {
  const p = props.post;
  const published =
    props.dateType === 'guild' ? p.published_at_guild : p.published_at_global;
  const fallback = p.updated_at || p.created_at;
  return published || fallback;
});

const displayTime = computed(() => formatRelativeTime(displayIso.value));

const displayName = computed(
  () => props.post.author_name ?? 'Неизвестный персонаж'
);
const avatarUrl = computed(
  () => props.post.author_avatar_url || null
);
const avatarFallback = computed(() =>
  (displayName.value || '??')
    .trim()
    .slice(0, 2)
    .toUpperCase()
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
          <p class="truncate text-sm font-medium text-gray-600">
            {{ displayName }}
          </p>
          <h3
            class="truncate text-xl font-bold text-foreground/90 cursor-pointer hover:underline"
            :title="post.title || 'Без заголовка'"
            @click.stop="emit('titleClick')"
          >
            {{ post.title || 'Без заголовка' }}
          </h3>
          <span class="shrink-0 text-xs text-muted-foreground">
            {{ displayTime }}
          </span>
        </div>
      </div>
    </header>
    <div
      v-if="showPreview && isPreviewHtml"
      ref="previewContainerRef"
      class="prose prose-sm max-w-none text-md dark:prose-invert [&_p]:my-1 [&_p]:first:mt-0 [&_p]:last:mb-0 [&_a]:text-blue-600 [&_a]:underline [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6"
      v-html="displayBody(true)"
    />
    <p
      v-else-if="showPreview"
      class="text-sm text-muted-foreground line-clamp-2"
    >
      {{ displayBody(true) }}
    </p>
    <div
      v-else-if="isHtmlBody"
      class="prose prose-sm max-w-none text-sm dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6"
      v-html="post.body ?? ''"
    />
    <p v-else class="whitespace-pre-wrap text-sm">
      {{ post.body ?? '' }}
    </p>
    <div
      v-if="post.views_count != null"
      class="mt-3 flex items-center gap-1.5 border-t pt-3 text-xs text-muted-foreground"
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
  </article>
</template>

