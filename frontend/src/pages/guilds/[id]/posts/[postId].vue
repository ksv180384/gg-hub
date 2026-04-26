<script setup lang="ts">
import { Button, Separator } from '@/shared/ui';
import PostCardFull from '@/shared/ui/post/PostCardFull.vue';
import PostComments from './PostComments.vue';
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
const commentsCount = ref<number | null>(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

/** Право публиковать/отклонять/блокировать посты в гильдии */
const canModeratePosts = computed(
  () => !!guild.value?.my_permission_slugs?.includes('publikovat-post')
);

const canComment = computed(() => !!guild.value);

const isPendingInGuild = computed(
  () => post.value?.status_guild === 'pending'
);

const isPublishedInGuild = computed(
  () => post.value?.status_guild === 'published'
);

const isGuildBlocked = computed(() => post.value?.status_guild === 'blocked');
const isGlobalBlocked = computed(() => post.value?.status_global === 'blocked');

/** Показывать кнопку «Заблокировать»: право publikovat-post, пост ещё не заблокирован для гильдии */
const canBlock = computed(
  () =>
    canModeratePosts.value &&
    !!post.value &&
    !isGuildBlocked.value &&
    (isPublishedInGuild.value || isPendingInGuild.value)
);

/** Показывать «Разблокировать»: пост заблокирован только для гильдии (не в общем журнале) */
const canUnblock = computed(
  () =>
    canModeratePosts.value &&
    !!post.value &&
    isGuildBlocked.value &&
    !isGlobalBlocked.value
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

    // Не показываем неопубликованные посты обычным участникам.
    // Модератор с правом publikovat-post может просматривать pending/draft/hidden/blocked и т.д.
    if (post.value?.status_guild !== 'published' && !canModeratePosts.value) {
      redirectToGuildPosts();
      return;
    }
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

async function block() {
  if (!guildId.value || !postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.blockGuildPost(guildId.value, postId.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось заблокировать пост';
  } finally {
    submitting.value = false;
  }
}

async function unblock() {
  if (!guildId.value || !postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.unblockGuildPost(guildId.value, postId.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось разблокировать пост';
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
        <h1 class="text-2xl font-bold tracking-tight">
          Пост гильдии
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
            date-type="guild"
            :comments-count="commentsCount ?? post.comments_count"
          />

          <div
            v-if="(canModeratePosts && isPendingInGuild) || canBlock || canUnblock"
            class="flex flex-wrap items-center justify-end gap-3 pt-2"
          >
            <span v-if="canModeratePosts && isPendingInGuild" class="text-xs text-muted-foreground">
              Статус: ожидает публикации
            </span>
            <span v-else-if="canUnblock" class="text-xs text-muted-foreground">
              Заблокировано для гильдии
            </span>
            <template v-if="canModeratePosts && isPendingInGuild">
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
            </template>
            <Button
              v-if="canUnblock"
              variant="outline"
              size="sm"
              :disabled="submitting"
              @click="unblock"
            >
              {{ submitting ? 'Обработка…' : 'Разблокировать' }}
            </Button>
            <Button
              v-if="canBlock"
              variant="destructive"
              size="sm"
              :disabled="submitting"
              @click="block"
            >
              {{ submitting ? 'Обработка…' : 'Заблокировать' }}
            </Button>
          </div>

          <PostComments
            v-if="post"
            class="mt-8"
            :guild-id="guildId"
            :post-id="post.id"
            :can-comment="canComment"
            :my-characters="guild?.my_characters ?? []"
            @update:comments-count="commentsCount = $event"
          />
        </template>
      </div>
    </div>
  </div>
</template>

