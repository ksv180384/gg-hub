<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Badge, Button, Separator } from '@/shared/ui';
import { postsApi, type Post } from '@/shared/api/postsApi';

const posts = ref<Post[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

function formatDate(iso: string | null): string {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });
  } catch {
    return iso;
  }
}

function displayPreview(post: Post): string {
  if (post.preview && post.preview.trim()) return post.preview;
  const text = (post.body || '').replace(/<[^>]+>/g, '').trim();
  if (text.length <= 160) return text;
  return text.slice(0, 160) + '…';
}

function isPreviewHtml(post: Post): boolean {
  return !!(post.preview && post.preview.trim().startsWith('<'));
}

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
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-3xl">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="mb-1 text-3xl font-bold tracking-tight">Мои посты</h1>
          <p class="text-muted-foreground">
            Список ваших постов.
          </p>
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
      <ul v-else class="space-y-6">
        <li
          v-for="(post, i) in posts"
          :key="post.id"
          class="animate-in fade-in slide-in-from-bottom-3"
          :style="{ animationDelay: `${i * 80}ms`, animationDuration: '400ms', animationFillMode: 'backwards' }"
        >
          <Card class="overflow-hidden transition-all hover:shadow-md">
            <CardHeader class="pb-2">
              <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline" class="text-xs">
                  Общие: {{ effectiveStatusLabel(post.is_visible_global, post.status_global_label, 'Не включено') }}
                </Badge>
                <Badge variant="outline" class="text-xs">
                  Гильдия: {{ effectiveStatusLabel(post.is_visible_guild, post.status_guild_label, 'Не включено') }}
                </Badge>
                <span class="text-xs text-muted-foreground">
                  {{ formatDate(post.published_at_global ?? post.published_at_guild ?? post.created_at) }}
                </span>
              </div>
              <CardTitle class="mt-2 text-lg flex items-center justify-between gap-3">
                <span>{{ post.title || 'Без названия' }}</span>
                <RouterLink
                  v-if="!isPostBlocked(post)"
                  :to="{ name: 'my-posts-edit', params: { id: post.id } }"
                >
                  <Button variant="outline" size="xs">
                    Редактировать
                  </Button>
                </RouterLink>
                <span v-else class="text-xs text-muted-foreground">Редактирование недоступно</span>
              </CardTitle>
            </CardHeader>
            <CardContent class="pt-0">
              <div
                v-if="isPreviewHtml(post)"
                class="prose prose-sm max-w-none text-md dark:prose-invert [&_p]:my-1 [&_p]:first:mt-0 [&_p]:last:mb-0 [&_a]:text-blue-600 [&_a]:underline [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6"
                v-html="displayPreview(post)"
              />
              <p v-else class="text-sm text-muted-foreground line-clamp-2">
                {{ displayPreview(post) }}
              </p>
            </CardContent>
          </Card>
        </li>
      </ul>
    </div>
  </div>
</template>
