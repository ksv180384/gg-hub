<script setup lang="ts">
import { Avatar } from '@/shared/ui';
import { useRouter } from 'vue-router';
import type { Post } from '@/shared/api/postsApi';
import { computed } from 'vue';
import { formatRelativeTime } from '@/shared/lib/relativeTime';

interface Props {
  post: Post;
  /**
   * Какую дату считать датой публикации:
   * - guild  — published_at_guild → updated_at → created_at
   * - global — published_at_global → updated_at → created_at
   */
  dateType?: 'guild' | 'global';
}

const props = withDefaults(defineProps<Props>(), {
  dateType: 'guild',
});

const emit = defineEmits<{
  (e: 'titleClick'): void;
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
  () => props.post.author_name ?? props.post.character?.name ?? 'Неизвестный персонаж'
);
const avatarUrl = computed(
  () => props.post.author_avatar_url || props.post.character?.avatar_url || null
);
const avatarFallback = computed(() =>
  (displayName.value || '??')
    .trim()
    .slice(0, 2)
    .toUpperCase()
);
</script>

<template>
  <article class="rounded-lg border bg-card p-4 shadow-sm">
    <header class="mb-2 flex items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-3">
        <Avatar
          class="h-9 w-9"
          :src="avatarUrl || undefined"
          :alt="displayName"
          :fallback="avatarFallback"
        />
        <div class="min-w-0">
          <p class="truncate text-sm font-medium">
            {{ displayName }}
          </p>
          <h3
            class="truncate text-sm text-foreground/90 cursor-pointer hover:underline"
            @click.stop="emit('titleClick')"
          >
            {{ post.title || 'Без заголовка' }}
          </h3>
        </div>
      </div>
      <span class="shrink-0 text-xs text-muted-foreground">
        {{ displayTime }}
      </span>
    </header>
    <p class="whitespace-pre-wrap text-sm">
      {{ post.body }}
    </p>
  </article>
</template>

