<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, PostCardFull } from '@/shared/ui';
import type { ApiError } from '@/shared/api/errors';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const guildId = computed(() => Number(route.params.id));
const postId = computed(() => Number(route.params.postId));

const guild = ref<Guild | null>(null);
const post = ref<Post | null>(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

const canModeratePosts = computed(
  () => !!guild.value?.my_permission_slugs?.includes('publikovat-post')
);

const isPendingInGuild = computed(
  () => post.value?.status_guild === 'pending'
);

function redirectToGuildPosts() {
  router.replace({
    name: 'guild-show',
    params: { id: String(guildId.value) },
  });
}

async function loadData() {
  loading.value = true;
  error.value = null;
  try {
    if (!guildId.value || !postId.value) {
      redirectToGuildPosts();
      return;
    }
    guild.value = await guildsApi.getGuildForSettings(guildId.value);
    post.value = await postsApi.getGuildPost(guildId.value, postId.value);
  } catch (e) {
    const apiError = e as ApiError;
    if (apiError?.status === 403 || apiError?.status === 404) {
      redirectToGuildPosts();
      return;
    }
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить пост';
  } finally {
    loading.value = false;
  }
}

async function publish() {
  if (!guildId.value || !postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.publishGuildPost(guildId.value, postId.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось опубликовать пост';
  } finally {
    submitting.value = false;
  }
}

async function reject() {
  if (!guildId.value || !postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.rejectGuildPost(guildId.value, postId.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отклонить пост';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  loadData();
});
</script>

<template>
  <div class="container py-6 md:py-8">
    <div class="mx-auto max-w-3xl space-y-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold tracking-tight">
            Пост гильдии
          </h1>
          <p v-if="guild" class="text-sm text-muted-foreground">
            Гильдия: {{ guild.name }}
          </p>
        </div>
        <Button variant="outline" size="sm" @click="router.back()">
          Назад
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">
            Полная версия поста
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <p v-if="loading" class="text-sm text-muted-foreground">
            Загрузка поста…
          </p>
          <p v-else-if="error" class="text-sm text-destructive">
            {{ error }}
          </p>
          <p v-else-if="!post" class="text-sm text-muted-foreground">
            Пост не найден.
          </p>
          <template v-else>
            <PostCardFull :post="post" date-type="guild" />

            <div
              v-if="canModeratePosts && isPendingInGuild"
              class="flex flex-wrap items-center justify-end gap-3 pt-2"
            >
              <span class="text-xs text-muted-foreground">
                Статус: ожидает публикации
              </span>
              <Button
                variant="outline"
                size="sm"
                :disabled="submitting"
                @click="reject"
              >
                {{ submitting ? 'Обработка…' : 'Отклонить' }}
              </Button>
              <Button
                size="sm"
                :disabled="submitting"
                @click="publish"
              >
                {{ submitting ? 'Обработка…' : 'Опубликовать' }}
              </Button>
            </div>

            <div
              v-else-if="canModeratePosts"
              class="flex justify-end pt-2 text-xs text-muted-foreground"
            >
              Статус в гильдии: {{ post.status_guild || '—' }}
            </div>
          </template>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

