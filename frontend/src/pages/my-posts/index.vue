<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import PostCardPreview from '@/shared/ui/post/PostCardPreview.vue';
import { Badge, Button, Separator } from '@/shared/ui';
import { postsApi, type Post } from '@/shared/api/postsApi';

const posts = ref<Post[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

function effectiveStatusLabel(
  isVisible: boolean,
  statusLabel: string | undefined,
  hiddenLabel: string
): string {
  return isVisible ? (statusLabel ?? '—') : hiddenLabel;
}

/** Полностью заблокирован (общие и гильдия) — редактирование недоступно */
function isPostBlocked(post: Post): boolean {
  return post.status_global === 'blocked' && post.status_guild === 'blocked';
}

function postTo(post: Post) {
  if (isPostBlocked(post)) return null;
  return { name: 'my-posts-edit' as const, params: { id: String(post.id) } };
}

async function loadPosts() {
  loading.value = true;
  error.value = null;
  try {
    posts.value = await postsApi.getMyPosts();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить посты';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadPosts();
});
</script>

<template>
  <div class="space-y-4">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="mb-1 text-3xl font-bold tracking-tight">Мои посты</h1>
          </div>
          <RouterLink :to="{ name: 'my-posts-create' }">
            <Button class="min-h-11 min-w-[44px] shrink-0 touch-manipulation">
              Добавить пост
            </Button>
          </RouterLink>
        </div>
        <Separator class="my-8" />

        <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
        <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
        <p v-else-if="posts.length === 0" class="text-sm text-muted-foreground">
          У вас пока нет постов.
        </p>
        <div v-else class="space-y-4">
          <PostCardPreview
            v-for="(post, i) in posts"
            :key="post.id"
            class="animate-in fade-in slide-in-from-bottom-3"
            :style="{ animationDelay: `${i * 80}ms`, animationDuration: '400ms', animationFillMode: 'backwards' }"
            :post="post"
            date-type="global"
            :post-to="postTo(post)"
          >
            <template #headerRight>
              <div class="flex flex-wrap justify-end gap-2">
                <Badge variant="outline" class="text-xs">
                  Общие: {{ effectiveStatusLabel(post.is_visible_global, post.status_global_label, 'Не включено') }}
                </Badge>
                <Badge variant="outline" class="text-xs">
                  Гильдия: {{ effectiveStatusLabel(post.is_visible_guild, post.status_guild_label, 'Не включено') }}
                </Badge>
              </div>
            </template>

            <template #footerRight>
              <RouterLink
                v-if="!isPostBlocked(post)"
                v-slot="{ href, navigate }"
                :to="{ name: 'my-posts-edit', params: { id: post.id } }"
                custom
              >
                <a
                  :href="href"
                  class="inline-flex items-center gap-2 text-sm font-medium text-primary transition-colors hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  @click="navigate"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                  >
                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                    <path d="m15 5 4 4" />
                  </svg>
                  Редактировать
                </a>
              </RouterLink>
              <span v-else class="text-right text-xs text-muted-foreground">Редактирование недоступно</span>
            </template>
          </PostCardPreview>
  </div>
  </div>
</template>
