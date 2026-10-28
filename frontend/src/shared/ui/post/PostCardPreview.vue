<script setup lang="ts">
import { Avatar } from '@/shared/ui';
import type { Post } from '@/shared/api/postsApi';
import { computed, ref } from 'vue';
import { RouterLink, type RouteLocationRaw } from 'vue-router';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import { useVideoPlaybackTracking } from '@/shared/lib/useVideoPlaybackTracking';
import DeletedGuildMarker from './DeletedGuildMarker.vue';
import PostPreviewContent from './PostPreviewContent.vue';
import { ArrowUpRight, Eye, MessageSquare } from '@lucide/vue';

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
  /** Маршрут просмотра поста (для SEO-ссылки). */
  postTo?: RouteLocationRaw | null;
  /** Маршрут к комментариям поста (для SEO-ссылки). */
  commentsTo?: RouteLocationRaw | null;
}

const props = withDefaults(defineProps<Props>(), {
  dateType: 'guild',
});

function displayBody(): string {
  if (props.post.preview?.trim()) return props.post.preview;
  const raw = (props.post.body ?? '').trim();
  if (!raw) return '';
  const text = raw.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
  if (!text) return '';
  if (text.length <= 240) return text;
  return text.slice(0, 240) + '…';
}

const isPreviewHtml = computed(
  () => (props.post.preview ?? '').trim().startsWith('<')
);

const emit = defineEmits<{
  (e: 'authorClick', userId: number): void;
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
const showDeletedGuildMarker = computed(
  () => !!props.post.is_global_as_guild && !!props.post.guild_deleted_at
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
  <article
    class="post-card overflow-hidden rounded-2xl border bg-card text-card-foreground"
  >
    <header class="flex items-start justify-between gap-3 px-5 pt-5 pb-4 sm:px-6 sm:pt-6">
      <div class="flex min-w-0 items-start gap-3">
        <Avatar
          class="h-10 w-10 shrink-0 rounded-full ring-4 ring-muted/60"
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
            <DeletedGuildMarker
              v-if="showDeletedGuildMarker"
              :deleted-at="post.guild_deleted_at"
            />
          </div>
          <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <time :datetime="displayIso ?? undefined" class="text-xs text-muted-foreground">
              {{ displayTime }}
            </time>
            <span
              v-if="showGame && post.game_name"
              class="text-xs text-muted-foreground"
            >
              {{ post.game_name }}
            </span>
          </div>
          <div v-if="showStatus" class="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5">
            <span
              v-if="post.status_global"
              class="text-[11px] text-muted-foreground"
              title="Статус в общем журнале"
            >
              общие: {{ post.status_global_label ?? post.status_global }}
            </span>
            <span
              v-if="post.status_guild"
              class="text-[11px] text-muted-foreground"
              title="Статус в гильдии"
            >
              гильдия: {{ post.status_guild_label ?? post.status_guild }}
            </span>
          </div>
        </div>
      </div>
      <div v-if="$slots.headerRight" class="shrink-0">
        <slot name="headerRight" />
      </div>
    </header>

    <div class="px-5 pb-4 sm:px-6">
      <h3
        class="post-card-title text-xl leading-snug font-semibold tracking-tight text-foreground sm:text-2xl"
        :title="post.title ?? ''"
      >
        <RouterLink
          v-if="postTo"
          :to="postTo"
          class="text-inherit transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-sm"
        >
          {{ post.title }}
        </RouterLink>
        <span v-else>
          {{ post.title }}
        </span>
      </h3>
    </div>

    <div ref="previewContainerRef" class="px-5 pb-5 sm:px-6 sm:pb-6">
      <PostPreviewContent
        :content="displayBody()"
        :is-html="isPreviewHtml"
        :post-to="postTo"
      />
    </div>

    <footer
      v-if="post.views_count != null || post.comments_count != null || postTo || $slots.footerRight"
      class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-border/70 px-5 py-4 sm:px-6"
    >
      <div class="flex items-center gap-5 text-xs font-medium text-muted-foreground">
        <span
          v-if="post.views_count != null"
          class="inline-flex items-center gap-2"
          :aria-label="`Просмотры: ${post.views_count}`"
          title="Просмотры"
        >
          <Eye class="size-4" aria-hidden="true" />
          {{ post.views_count }}
        </span>
        <component
          :is="commentsTo ? RouterLink : 'span'"
          v-if="post.comments_count != null"
          :to="commentsTo || undefined"
          class="inline-flex items-center gap-2 rounded-sm"
          :class="{ 'transition-colors hover:text-primary focus-visible:outline-2 focus-visible:outline-ring': commentsTo }"
          :aria-label="`Комментарии: ${post.comments_count}`"
          title="Комментарии"
        >
          <MessageSquare class="size-4" aria-hidden="true" />
          {{ post.comments_count }}
        </component>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <slot name="footerRight" />
        <RouterLink
          v-if="postTo"
          :to="postTo"
          class="inline-flex items-center gap-2 rounded-lg bg-primary/10 px-3.5 py-2 text-xs font-semibold text-foreground transition-colors hover:bg-primary/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          :aria-label="`Читать пост: ${post.title ?? ''}`"
        >
          Читать пост
          <ArrowUpRight class="size-4 text-primary" aria-hidden="true" />
        </RouterLink>
      </div>
    </footer>
  </article>
</template>

<style scoped>
.post-card {
  box-shadow: 0 2px 8px -4px rgb(15 23 42 / 0.12);
  transition: border-color 180ms ease, box-shadow 180ms ease;
}

.post-card:hover {
  border-color: color-mix(in oklab, var(--primary) 25%, var(--border));
  box-shadow: 0 8px 24px -12px rgb(15 23 42 / 0.2);
}

.post-card-title {
  overflow-wrap: anywhere;
}

@media (prefers-reduced-motion: reduce) {
  .post-card,
  .post-card :deep(a) {
    transition: none;
  }
}
</style>
