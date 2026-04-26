<script setup lang="ts">
import { Button } from '@/shared/ui';
import PostCardFull from '@/shared/ui/post/PostCardFull.vue';
import type { ApiError } from '@/shared/api/errors';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import PostComments from './PostComments.vue';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const postId = computed(() => Number(route.params.postId));

const post = ref<Post | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const myCharacters = ref<Character[]>([]);
const commentsCount = ref<number | null>(null);

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
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить пост';
  } finally {
    loading.value = false;
  }
}

onMounted(loadPost);
</script>

<template>
  <div class="container py-6 md:py-8">
    <div class="mx-auto max-w-3xl space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">
          Пост
        </h1>
        <Button variant="link" size="sm" @click="router.back()" class="cursor-pointer">
          Назад
        </Button>
      </div>

      <div class="space-y-4">
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
        </template>
      </div>
    </div>
  </div>
</template>

