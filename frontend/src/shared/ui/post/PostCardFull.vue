<script setup lang="ts">
import { Avatar } from '@/shared/ui';
import type { Post } from '@/shared/api/postsApi';
import { computed } from 'vue';
import { formatRelativeTime } from '@/shared/lib/relativeTime';

interface Props {
  post: Post;
  /** Какую дату считать датой публикации: guild | global */
  dateType?: 'guild' | 'global';
}

const props = withDefaults(defineProps<Props>(), {
  dateType: 'guild',
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
            class="truncate text-xl font-bold text-foreground/90"
            :title="post.title || 'Без заголовка'"
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
      v-if="isHtmlBody"
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
