<script setup lang="ts">
import PostCardPreview from '@/shared/ui/post/PostCardPreview.vue';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { Skeleton } from '@/shared/ui';
import { ref, computed, watch } from 'vue';
import { useSiteContextStore } from '@/stores/siteContext';
import { applyPageSeo, getSiteOrigin } from '@/shared/lib/usePageSeo';
import GgHubJournalBanner from '@/widgets/journal-promo/GgHubJournalBanner.vue';

const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const posts = ref<Post[]>([]);
const loading = ref(true);

const siteOrigin = getSiteOrigin();

let cleanupSeo: (() => void) | null = null;

async function loadJournal() {
  const g = game.value;
  if (!g?.id) {
    posts.value = [];
    loading.value = false;
    return;
  }
  loading.value = true;
  try {
    posts.value = await postsApi.getGlobalJournalPosts(g.id);
  } catch {
    posts.value = [];
  } finally {
    loading.value = false;
  }
}

watch(() => game.value?.id, () => {
  loadJournal();
}, { immediate: true });

watch(
  () => game.value?.name,
  (name) => {
    if (typeof window === 'undefined') return;

    const gameName = name?.trim();
    const title = gameName ? `Журнал — ${gameName} — gg-hub` : 'Журнал — gg-hub';
    const description = gameName
      ? `Журнал gg-hub: новости, гайды и обновления по игре ${gameName}. Следите за событиями и публикациями сообщества.`
      : 'Журнал gg-hub: новости, гайды и обновления. Следите за событиями и публикациями сообщества.';
    const keywords = gameName
      ? `журнал ${gameName}, новости ${gameName}, гайды ${gameName}, ${gameName} гильдии, gg-hub`
      : 'журнал, новости, гайды, MMORPG, gg-hub';
    const canonicalUrl = `${siteOrigin}/`;

    // Обновляем мета при смене игры (или первичной загрузке контекста).
    cleanupSeo?.();
    cleanupSeo = applyPageSeo({
      title,
      description,
      canonicalUrl,
      keywords,
      ogType: 'website',
    });
  },
  { immediate: true },
);

function onViewRecorded(postId: number) {
  const p = posts.value.find((x) => x.id === postId);
  if (p) p.views_count = (p.views_count ?? 0) + 1;
}

function postTo(post: Post) {
  return { name: 'global-post-show' as const, params: { postId: String(post.id) } };
}

function commentsTo(post: Post) {
  return { ...postTo(post), hash: '#comments' };
}
</script>

<template>
  <div class="space-y-4">
        <p v-if="!game?.id" class="text-sm text-muted-foreground">
          Выберите игру для просмотра журнала.
        </p>
        <template v-else>
          <div v-if="loading" class="space-y-4" aria-busy="true" aria-live="polite">
            <article
              v-for="n in 4"
              :key="n"
              class="overflow-hidden bg-accent/30 rounded-[calc(var(--radius)-2px)] border shadow-sm"
            >
              <div class="flex items-start justify-between gap-3 p-4 pb-2">
                <div class="flex min-w-0 items-start gap-3">
                  <Skeleton class="h-10 w-10 rounded-full shrink-0" />
                  <div class="min-w-0 space-y-2">
                    <Skeleton class="h-4 w-44" />
                    <Skeleton class="h-3 w-24" />
                  </div>
                </div>
              </div>
              <div class="px-4 pb-2">
                <Skeleton class="h-5 w-3/4" />
              </div>
              <div class="px-4 pb-3 space-y-2">
                <Skeleton class="h-3 w-full" />
                <Skeleton class="h-3 w-11/12" />
                <Skeleton class="h-3 w-10/12" />
              </div>
              <div class="flex items-center gap-4 border-t bg-card px-4 py-3">
                <Skeleton class="h-3 w-16" />
                <Skeleton class="h-3 w-16" />
              </div>
            </article>
          </div>
          <p v-else-if="posts.length === 0" class="text-sm text-muted-foreground">
            В журнале пока нет постов.
          </p>
          <div v-else class="space-y-4">
            <template v-for="(post, index) in posts" :key="post.id">
              <PostCardPreview
                :post="post"
                :guild-id="post.guild_id ?? undefined"
                date-type="global"
                :post-to="postTo(post)"
                :comments-to="commentsTo(post)"
                @view-recorded="onViewRecorded(post.id)"
              />
              <GgHubJournalBanner v-if="index === 0" variant="mobile" />
            </template>
          </div>
        </template>
  </div>
</template>
