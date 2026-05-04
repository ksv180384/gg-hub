<script setup lang="ts">
import { Button } from '@/shared/ui';
import PostCardFull from '@/shared/ui/post/PostCardFull.vue';
import type { ApiError } from '@/shared/api/errors';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import PostComments from './PostComments.vue';
import GgHubJournalBanner from '@/widgets/journal-promo/GgHubJournalBanner.vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { applyPageSeo, getSiteOrigin } from '@/shared/lib/usePageSeo';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const siteOrigin = getSiteOrigin();

const postId = computed(() => Number(route.params.postId));

const post = ref<Post | null>(null);
const loading = ref(true);
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

let seoCleanup: null | (() => void) = null;
function applySeoForPost(p: Post) {
  const titleBase = p.title?.trim() || 'Запись';
  const title = `${titleBase} — gg-hub`;
  const canonicalUrl = `${siteOrigin}${route.fullPath}`;
  const description = buildDescription(p);
  const keywords = buildKeywords(p);

  seoCleanup?.();
  seoCleanup = applyPageSeo({
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
  });
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
  loading.value = true;
  error.value = null;
  try {
    if (!postId.value) {
      redirectToJournal();
      return;
    }
    post.value = await postsApi.getGlobalPost(postId.value);
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
  <div class="container py-6 md:py-8">
    <div class="flex flex-col gap-8 lg:grid lg:grid-cols-[minmax(0,42rem)_minmax(0,1fr)] lg:gap-10">
      <div class="min-w-0 space-y-4">
        <p v-if="loading" class="text-sm text-muted-foreground">
          Загрузка…
        </p>
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
              <Button
                variant="ghost"
                size="sm"
                class="h-9 w-9 p-0"
                aria-label="Назад"
                title="Назад"
                @click="router.back()"
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
                  aria-hidden="true"
                >
                  <path d="M15 18l-6-6 6-6" />
                </svg>
              </Button>
            </div>

            <!-- Mobile: одна плавающая кнопка справа -->
            <div class="fixed top-[100px] right-8 z-30 md:hidden">
              <Button
                variant="ghost"
                size="sm"
                class="h-9 w-9 border border-border bg-background p-0 shadow-md"
                aria-label="Назад"
                title="Назад"
                @click="router.back()"
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
                  aria-hidden="true"
                >
                  <path d="M15 18l-6-6 6-6" />
                </svg>
              </Button>
            </div>

            <div class="min-w-0 w-full flex-1">
              <PostCardFull
                :post="post"
                date-type="global"
                :comments-count="commentsCount ?? post.comments_count"
              />

              <GgHubJournalBanner variant="mobile" class="mt-8" />

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

      <GgHubJournalBanner v-if="post" variant="desktop" />
    </div>
  </div>
</template>

