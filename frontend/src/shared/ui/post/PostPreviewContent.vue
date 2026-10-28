<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, type RouteLocationRaw } from 'vue-router';

const props = defineProps<{
  /** HTML превью уже очищен сервером, как и содержимое полной статьи. */
  content: string;
  isHtml: boolean;
  postTo?: RouteLocationRaw | null;
}>();

const hasVideo = computed(() => /<(?:iframe|video)\b/i.test(props.content));

// Keep video embeds in their original container for playback tracking.
// Moving the image tag itself preserves its URL and alt text on SSR and client.
const cover = computed(() => {
  if (!props.isHtml || !props.postTo || hasVideo.value) {
    return '';
  }

  return props.content.match(/<img\b[^>]*>/i)?.[0] ?? '';
});

const excerpt = computed(() => {
  if (!cover.value) {
    return props.content;
  }

  return props.content.replace(cover.value, '');
});

const isCompact = computed(() => !!props.postTo && !hasVideo.value);
</script>

<template>
  <div
    class="post-preview"
    :class="{ 'post-preview--with-cover': cover }"
  >
    <RouterLink
      v-if="cover && postTo"
      :to="postTo"
      class="post-preview-cover"
      aria-label="Открыть пост"
    >
      <div class="w-full" v-html="cover" />
    </RouterLink>
    <div
      v-if="isHtml"
      class="post-preview-rich"
      :class="{ 'post-preview-rich--compact': isCompact }"
      v-html="excerpt"
    />
    <p
      v-else-if="content"
      class="post-preview-text"
      :class="{ 'line-clamp-4': isCompact }"
    >
      {{ content }}
    </p>
  </div>
</template>

<style scoped>
.post-preview {
  display: grid;
  min-width: 0;
  gap: 1.5rem;
}

.post-preview-cover {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--muted);
}

.post-preview-cover:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 3px;
}

.post-preview-cover :deep(img) {
  display: block;
  width: 100% !important;
  height: 240px !important;
  max-width: 100% !important;
  margin: 0 !important;
  object-fit: contain;
}

.post-preview-rich,
.post-preview-text {
  min-width: 0;
  color: var(--muted-foreground);
  font-size: 0.9375rem;
  line-height: 1.75;
  overflow-wrap: anywhere;
}

.post-preview-rich--compact {
  max-height: 240px;
  overflow: hidden;
  mask-image: linear-gradient(to bottom, black calc(100% - 28px), transparent);
}

.post-preview-rich--compact:focus-within {
  max-height: none;
  mask-image: none;
}

.post-preview-rich :deep(p) {
  margin: 0 0 0.75rem;
}

.post-preview-rich :deep(p:empty) {
  display: none;
}

.post-preview-rich :deep(h2),
.post-preview-rich :deep(h3),
.post-preview-rich :deep(h4) {
  margin: 0.75rem 0 0.375rem;
  color: var(--foreground);
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.5;
}

.post-preview-rich :deep(> :first-child) {
  margin-top: 0;
}

.post-preview-rich :deep(strong) {
  color: var(--foreground);
  font-weight: 600;
}

.post-preview-rich :deep(a) {
  color: var(--foreground);
  text-decoration: underline;
  text-underline-offset: 3px;
}

.post-preview-rich :deep(ul),
.post-preview-rich :deep(ol) {
  margin: 0.5rem 0;
  padding-left: 1.25rem;
}

.post-preview-rich :deep(ul) {
  list-style-type: disc;
}

.post-preview-rich :deep(ol) {
  list-style-type: decimal;
}

.post-preview-rich :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 0.75rem;
}

.post-preview-rich :deep(iframe),
.post-preview-rich :deep(video) {
  width: 100%;
  max-width: 100%;
  aspect-ratio: 16 / 9;
  height: auto;
  border-radius: 0.75rem;
}

@media (min-width: 768px) {
  .post-preview--with-cover {
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    align-items: start;
  }
}

@media (max-width: 767px) {
  .post-preview {
    gap: 1rem;
  }

  .post-preview-cover :deep(img) {
    height: 210px !important;
  }

  .post-preview-rich--compact {
    max-height: 158px;
  }
}
</style>
