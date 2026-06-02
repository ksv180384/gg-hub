<script setup lang="ts">
import { BackIconButton, Skeleton } from '@/shared/ui';
import PostCardFull from '@/shared/ui/post/PostCardFull.vue';
import type { ApiError } from '@/shared/api/errors';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import PostComments from './PostComments.vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useSsrPageDataStore } from '@/stores/ssrPageData';
import { applyPageSeo, getSiteOrigin, type PageSeoOptions } from '@/shared/lib/usePageSeo';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const ssrPageData = useSsrPageDataStore();
const siteOrigin = getSiteOrigin();

const postId = computed(() => Number(route.params.postId));
const initialPost = ssrPageData.globalPost?.id === postId.value ? ssrPageData.globalPost : null;

const post = ref<Post | null>(initialPost);
const loading = ref(!initialPost);
const error = ref<string | null>(null);
const myCharacters = ref<Character[]>([]);
const commentsCount = ref<number | null>(null);

function stripHtmlToText(input: string): string {
  return input
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function buildDescription(p: Post): string {
  const raw = (p.preview ?? p.body ?? '').toString();
  const text = stripHtmlToText(raw);
  if (text.length >= 50) return text.slice(0, 170).trim();
  const base = p.game_name ? `Пост по игре ${p.game_name}.` : 'Пост в gg-hub.';
  return text ? `${text} ${base}`.trim() : base;
}

function buildKeywords(p: Post): string {
  const parts = [
    p.title ?? '',
    p.game_name ?? '',
    p.author_name ?? '',
    'gg-hub',
    'журнал',
    'пост',
  ]
    .map((s) => s.trim())
    .filter(Boolean);
  const seen: Record<string, true> = {};
  const unique: string[] = [];
  for (const part of parts) {
    if (!seen[part]) {
      seen[part] = true;
      unique.push(part);
    }
  }
  return unique.join(', ');
}

function buildGameOrigin(origin: string, gameSlug?: string | null): string {
  const slug = gameSlug?.trim();
  if (!slug) return origin;

  try {
    const url = new URL(origin);
    const baseDomain = ['gg-hub.local', 'gg-hub.ru'].find(
      (domain) => url.hostname === domain || url.hostname.endsWith(`.${domain}`),
    );
    if (!baseDomain) return origin;

    const port = url.port ? `:${url.port}` : '';
    return `${url.protocol}//${slug}.${baseDomain}${port}`;
  } catch {
    return origin;
  }
}

function buildCanonicalUrl(p: Post): string {
  return `${buildGameOrigin(siteOrigin, p.game_slug)}/posts/${p.id}`;
}

function redirectToCanonicalPostUrl(p: Post): boolean {
  if (typeof window === 'undefined') return false;

  const canonicalUrl = buildCanonicalUrl(p);
  const currentUrl = `${window.location.origin}${route.path}`;
  if (canonicalUrl === currentUrl) return false;

  window.location.replace(canonicalUrl);
  return true;
}

let seoCleanup: null | (() => void) = null;
function buildSeoOptionsForPost(p: Post): PageSeoOptions {
  const titleBase = p.title?.trim() || 'Запись';
  const title = `${titleBase} — gg-hub`;
  const canonicalUrl = buildCanonicalUrl(p);
  const description = buildDescription(p);
  const keywords = buildKeywords(p);

  return {
    title,
    description,
    canonicalUrl,
    ogType: 'article',
    keywords,
    jsonLd: {
      '@context': 'https://schema.org',
      '@type': 'Article',
      headline: titleBase,
      description,
      mainEntityOfPage: canonicalUrl,
      datePublished: p.published_at_global ?? p.created_at,
      dateModified: p.updated_at,
      author: p.author_name ? { '@type': 'Person', name: p.author_name } : undefined,
    },
  };
}

function applySeoForPost(p: Post) {
  const options = buildSeoOptionsForPost(p);
  seoCleanup?.();
  seoCleanup = applyPageSeo(options);
}

watch(
  post,
  (p) => {
    if (p) applySeoForPost(p);
  },
  { immediate: false }
);

onUnmounted(() => {
  seoCleanup?.();
  seoCleanup = null;
});

function redirectToJournal() {
  router.replace({ name: 'home' });
}

async function loadPost() {
  if (post.value?.id === postId.value) {
    loading.value = false;
    if (redirectToCanonicalPostUrl(post.value)) return;
    applySeoForPost(post.value);
    if (auth.isAuthenticated) {
      if (post.value.game_id != null) {
        myCharacters.value = await charactersApi.getCharacters(post.value.game_id);
      } else {
        myCharacters.value = await charactersApi.getCharacters();
      }
    }
    return;
  }

  loading.value = true;
  error.value = null;
  try {
    if (!postId.value) {
      redirectToJournal();
      return;
    }
    post.value = await postsApi.getGlobalPost(postId.value);
    if (redirectToCanonicalPostUrl(post.value)) return;
    applySeoForPost(post.value);
    if (auth.isAuthenticated) {
      if (post.value?.game_id != null) {
        myCharacters.value = await charactersApi.getCharacters(post.value.game_id);
      } else {
        myCharacters.value = await charactersApi.getCharacters();
      }
    } else {
      myCharacters.value = [];
    }
  } catch (e) {
    const apiError = e as ApiError;
    if (apiError?.status === 403 || apiError?.status === 404) {
      redirectToJournal();
      return;
    }
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить запись';
  } finally {
    loading.value = false;
  }
}

onMounted(loadPost);
</script>

<template>
  <div class="space-y-4">
        <article
          v-if="loading"
          class="overflow-hidden bg-accent/30 rounded-[calc(var(--radius)-2px)] border shadow-sm"
          aria-busy="true"
          aria-live="polite"
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
          <div class="px-4 pb-3">
            <Skeleton class="h-7 w-4/5" />
          </div>
          <div class="space-y-2 px-4 pb-6">
            <Skeleton class="h-3 w-full" />
            <Skeleton class="h-3 w-11/12" />
            <Skeleton class="h-3 w-full" />
            <Skeleton class="h-3 w-10/12" />
            <Skeleton class="h-3 w-full" />
            <Skeleton class="h-3 w-9/12" />
            <Skeleton class="h-3 w-full" />
            <Skeleton class="h-3 w-8/12" />
            <Skeleton class="h-3 w-full" />
            <Skeleton class="h-3 w-7/12" />
          </div>
          <div class="flex items-center gap-4 border-t bg-card px-4 py-3">
            <Skeleton class="h-3 w-16" />
            <Skeleton class="h-3 w-16" />
          </div>
        </article>
        <p v-else-if="error" class="text-sm text-destructive">
          {{ error }}
        </p>
        <p v-else-if="!post" class="text-sm text-muted-foreground">
          Запись не найдена.
        </p>
        <template v-else>
          <div class="relative flex flex-col md:flex-row md:items-start md:gap-3">
            <!-- Desktop: стрелка слева от поста -->
            <div class="sticky top-[100px] z-30 hidden shrink-0 self-start md:block">
              <BackIconButton
                aria-label="Назад"
                title="Назад"
                @click="router.back()"
              >
              </BackIconButton>
            </div>

            <!-- Mobile: одна плавающая кнопка справа -->
            <div class="fixed top-[100px] right-8 z-30 md:hidden">
              <BackIconButton
                aria-label="Назад"
                title="Назад"
                @click="router.back()"
              >
              </BackIconButton>
            </div>

            <div class="min-w-0 w-full flex-1">
              <PostCardFull
                :post="post"
                date-type="global"
                :comments-count="commentsCount ?? post.comments_count"
              />
              <PostComments
                v-if="post"
                class="mt-8"
                :post-id="post.id"
                :can-comment="auth.isAuthenticated"
                :my-characters="myCharacters"
                @update:comments-count="commentsCount = $event"
              />
            </div>
          </div>
        </template>
  </div>
</template>
