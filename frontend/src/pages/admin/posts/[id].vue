<script setup lang="ts">
import { Button, PostCardFull } from '@/shared/ui';
import type { ApiError } from '@/shared/api/errors';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { useAdminJournalStore } from '@/stores/adminJournal';
import { useAuthStore } from '@/stores/auth';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const adminJournal = useAdminJournalStore();

const postId = computed(() => Number(route.params.id));

const post = ref<Post | null>(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

const PERMISSION_PUBLISH = 'publikovat-post';
const PERMISSION_BLOCK = 'blokirovat-posty';

const isPendingInGuild = computed(() => post.value?.status_guild === 'pending');
const isPendingGlobal = computed(() => post.value?.status_global === 'pending');
const hasGuild = computed(() => (post.value?.guild_id ?? 0) > 0);
const canPublishReject = computed(() => auth.hasPermission(PERMISSION_PUBLISH));
const canBlockPosts = computed(() => auth.hasPermission(PERMISSION_BLOCK));
const canModerate = computed(
  () =>
    canPublishReject.value &&
    (isPendingGlobal.value || (hasGuild.value && isPendingInGuild.value))
);
const isPublished = computed(
  () =>
    post.value?.status_global === 'published' ||
    post.value?.status_guild === 'published'
);
/** В общем журнале статус «Опубликован» — тогда показываем кнопки «Скрыть» и «Заблокировать» */
const isPublishedInGlobal = computed(() => post.value?.status_global === 'published');
const isBlocked = computed(
  () => post.value?.status_global === 'blocked' || post.value?.status_guild === 'blocked'
);
const isBlockedForGlobal = computed(() => post.value?.status_global === 'blocked');

const canBlock = computed(
  () =>
    // canBlockPosts.value &&
    // !!post.value &&
    isPublishedInGlobal.value //&&
    // !isBlockedForGlobal.value
);

const canHide = computed(
  () =>
    // canBlockPosts.value &&
    // !!post.value &&
    isPublishedInGlobal.value// &&
    // !isBlocked.value
);

/** Показывать «Разблокировать» только если пост заблокирован в общем журнале; при блокировке только в гильдии разблокировка — в гильдии */
const canUnblock = computed(
  () => canBlockPosts.value && !!post.value && isBlockedForGlobal.value
);

function redirectToJournal() {
  router.replace({ name: 'admin-journal' });
}

async function loadPost() {
  loading.value = true;
  error.value = null;
  try {
    if (!postId.value) {
      redirectToJournal();
      return;
    }
    post.value = await postsApi.getAdminPost(postId.value);
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

async function publish() {
  if (!postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.publishAdminPost(postId.value);
    await adminJournal.refreshPendingCount();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось опубликовать пост';
  } finally {
    submitting.value = false;
  }
}

async function reject() {
  if (!postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.rejectAdminPost(postId.value);
    await adminJournal.refreshPendingCount();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отклонить пост';
  } finally {
    submitting.value = false;
  }
}

async function block() {
  if (!postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.blockAdminPost(postId.value);
    await adminJournal.refreshPendingCount();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось заблокировать пост';
  } finally {
    submitting.value = false;
  }
}

async function hide() {
  if (!postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.hideAdminPost(postId.value);
    await adminJournal.refreshPendingCount();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось скрыть пост';
  } finally {
    submitting.value = false;
  }
}

async function unblock() {
  if (!postId.value) return;
  submitting.value = true;
  error.value = null;
  try {
    post.value = await postsApi.unblockAdminPost(postId.value);
    await adminJournal.refreshPendingCount();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось разблокировать пост';
  } finally {
    submitting.value = false;
  }
}

function goToUser(userId: number) {
  router.push({ name: 'admin-users-show', params: { id: String(userId) } });
}

onMounted(loadPost);
</script>

<template>
  <div class="container py-6 md:py-8">
    <div class="mx-auto max-w-3xl space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Пост</h1>
        <Button variant="link" size="sm" class="cursor-pointer" @click="router.push({ name: 'admin-journal' })">
          Назад
        </Button>
      </div>

      <div class="space-y-4">
        <p v-if="loading" class="text-sm text-muted-foreground">Загрузка поста…</p>
        <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
        <p v-else-if="!post" class="text-sm text-muted-foreground">Пост не найден.</p>
        <template v-else>
          <PostCardFull
            :post="post"
            date-type="global"
            :show-status="true"
            :author-user-id="post.user_id ?? undefined"
            :show-game="true"
            @author-click="goToUser"
          />

          <div
            v-if="canModerate || canBlock || canHide || canUnblock"
            class="flex flex-wrap items-center justify-end gap-3 pt-2"
          >
<!--            <span v-if="canModerate" class="text-xs text-muted-foreground">-->
<!--              {{ isPendingGlobal && isPendingInGuild ? 'Ожидает публикации (общие и гильдия)' : isPendingGlobal ? 'Ожидает публикации в общий журнал' : 'Ожидает публикации в гильдии' }}-->
<!--            </span>-->
<!--            <span v-else-if="canUnblock" class="text-xs text-muted-foreground">-->
<!--              Пост заблокирован-->
<!--            </span>-->
<!--            <span v-else-if="isPublished && (canHide || canBlock)" class="text-xs text-muted-foreground">-->
<!--              Опубликован-->
<!--            </span>-->
            <template v-if="canModerate">
              <Button variant="outline" size="sm" :disabled="submitting" @click="reject">
                {{ submitting ? 'Обработка…' : 'Отклонить' }}
              </Button>
              <Button size="sm" :disabled="submitting" @click="publish">
                {{ submitting ? 'Обработка…' : 'Опубликовать' }}
              </Button>
            </template>
            <Button
              v-if="canHide"
              variant="outline"
              size="sm"
              :disabled="submitting"
              @click="hide"
            >
              {{ submitting ? 'Обработка…' : 'Скрыть' }}
            </Button>
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
        </template>
      </div>
    </div>
  </div>
</template>
