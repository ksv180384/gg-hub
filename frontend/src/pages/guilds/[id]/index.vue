<script setup lang="ts">
import { Button } from '@/shared/ui';
import PostCardPreview from '@/shared/ui/post/PostCardPreview.vue';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import type { ApiError } from '@/shared/api/errors';
import { postsApi, type Post } from '@/shared/api/postsApi';
import NotFoundPage from '@/pages/not-found/index.vue';
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const guild = ref<Guild | null>(null);
const publishedPosts = ref<Post[]>([]);
const pendingPosts = ref<Post[]>([]);
const loadingGuild = ref(true);
const loadingPublished = ref(true);
const loadingPending = ref(false);
const showPending = ref(false);
const showBlocked = ref(false);
/** Нет доступа к журналу (404 API) — показываем UI «не найдено» без смены URL. */
const guildJournalNotFound = ref(false);
const publishedPostsError = ref<string | null>(null);
const blockedPostsError = ref<string | null>(null);

const canModeratePosts = computed(
  () => !!guild.value?.my_permission_slugs?.includes('publikovat-post')
);

async function loadGuildJournal() {
  if (!guildId.value) return;

  guildJournalNotFound.value = false;
  guild.value = null;
  publishedPosts.value = [];
  publishedPostsError.value = null;
  pendingPosts.value = [];
  showPending.value = false;
  showBlocked.value = false;
  loadingGuild.value = true;
  loadingPublished.value = true;
  loadingPending.value = false;

  try {
    // Используем настройки гильдии, чтобы получить my_permission_slugs
    guild.value = await guildsApi.getGuildForSettings(guildId.value);
  } catch (e) {
    guild.value = null;
    if ((e as ApiError).status === 404) {
      guildJournalNotFound.value = true;
    }
  } finally {
    loadingGuild.value = false;
  }

  if (guildJournalNotFound.value) {
    loadingPublished.value = false;
    publishedPosts.value = [];
    return;
  }

  try {
    publishedPosts.value = await postsApi.getGuildPosts(guildId.value);
  } catch (e) {
    publishedPosts.value = [];
    if ((e as ApiError).status === 404) {
      guildJournalNotFound.value = true;
    } else {
      publishedPostsError.value =
        e instanceof Error ? e.message : 'Не удалось загрузить журнал.';
    }
  } finally {
    loadingPublished.value = false;
  }

  if (guildJournalNotFound.value) {
    return;
  }

  if (canModeratePosts.value) {
    loadingPending.value = true;
    try {
      pendingPosts.value = await postsApi.getGuildPendingPosts(guildId.value);
    } finally {
      loadingPending.value = false;
    }
  }
}

const blockedPosts = ref<Post[]>([]);
const loadingBlocked = ref(false);

async function loadBlockedPosts() {
  if (!guildId.value || !canModeratePosts.value) return;
  loadingBlocked.value = true;
  blockedPostsError.value = null;
  try {
    blockedPosts.value = await postsApi.getGuildPosts(guildId.value, { filter: 'blocked' });
  } catch (e) {
    blockedPosts.value = [];
    if ((e as ApiError).status === 404) {
      guildJournalNotFound.value = true;
    } else {
      blockedPostsError.value =
        e instanceof Error ? e.message : 'Не удалось загрузить заблокированные посты.';
    }
  } finally {
    loadingBlocked.value = false;
  }
}

watch(guildId, () => {
  loadGuildJournal();
}, { immediate: true });

watch(showBlocked, (val) => {
  if (val && canModeratePosts.value) loadBlockedPosts();
});

const pendingCount = computed(() => pendingPosts.value.length);

function togglePending() {
  showBlocked.value = false;
  showPending.value = !showPending.value;
}

function toggleBlocked() {
  showPending.value = false;
  showBlocked.value = !showBlocked.value;
}

function onViewRecorded(postId: number) {
  const p = publishedPosts.value.find((x) => x.id === postId)
    ?? pendingPosts.value.find((x) => x.id === postId)
    ?? blockedPosts.value.find((x) => x.id === postId);
  if (p) p.views_count = (p.views_count ?? 0) + 1;
}
</script>

<template>
  <NotFoundPage v-if="guildJournalNotFound" />
  <div v-else class="py-6 space-y-4 max-w-2xl mx-auto">
    <div
      v-if="canModeratePosts"
      class="flex flex-wrap items-center gap-2 px-4"
    >
      <div class="min-w-0 flex-1">
        {{ showPending ? 'Посты, ожидающие публикации' : showBlocked ? 'Заблокированные посты' : 'Журнал гильдии' }}
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        class="cursor-pointer shrink-0"
        :class="
          showPending
            ? 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
            : ''
        "
        @click="togglePending"
      >
        Ожидают публикации
        <span
          class="ml-1 inline-flex h-5 min-w-[1.5rem] items-center justify-center rounded-full bg-background px-1 text-xs font-medium text-foreground"
        >
          {{ loadingPending ? '…' : pendingCount }}
        </span>
      </Button>
      <Button
        type="button"
        variant="outline"
        size="sm"
        class="cursor-pointer shrink-0"
        :class="
          showBlocked
            ? 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
            : ''
        "
        @click="toggleBlocked"
      >
        Заблокированные
      </Button>
    </div>

    <template v-if="showPending">
      <p v-if="loadingPending" class="text-sm text-muted-foreground">
        Загрузка постов на модерации…
      </p>
      <p v-else-if="pendingPosts.length === 0" class="text-sm text-muted-foreground">
        Нет постов, ожидающих публикации.
      </p>
      <div v-else class="space-y-4">
        <PostCardPreview
          v-for="post in pendingPosts"
          :key="post.id"
          :post="post"
          :guild-id="guildId"
          date-type="guild"
          @title-click="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) } })"
          @comments-click="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) }, hash: '#comments' })"
          @view-recorded="onViewRecorded(post.id)"
        />
      </div>
    </template>
    <template v-else-if="showBlocked">
      <p v-if="loadingBlocked" class="text-sm text-muted-foreground">
        Загрузка заблокированных постов…
      </p>
      <p v-else-if="blockedPostsError" class="text-sm text-destructive px-4">
        {{ blockedPostsError }}
      </p>
      <p v-else-if="blockedPosts.length === 0" class="text-sm text-muted-foreground">
        Нет заблокированных постов в гильдии.
      </p>
      <div v-else class="space-y-4">
        <PostCardPreview
          v-for="post in blockedPosts"
          :key="post.id"
          :post="post"
          :guild-id="guildId"
          date-type="guild"
          @title-click="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) } })"
          @comments-click="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) }, hash: '#comments' })"
          @view-recorded="onViewRecorded(post.id)"
        />
      </div>
    </template>
    <template v-else>
      <p v-if="loadingPublished" class="text-sm text-muted-foreground">Загрузка постов…</p>
      <p v-else-if="publishedPostsError" class="text-sm text-destructive px-4">
        {{ publishedPostsError }}
      </p>
      <p v-else-if="publishedPosts.length === 0" class="text-sm text-muted-foreground">
        В журнале гильдии пока нет постов.
      </p>
      <div v-else class="space-y-4">
        <PostCardPreview
          v-for="post in publishedPosts"
          :key="post.id"
          :post="post"
          :guild-id="guildId"
          date-type="guild"
          @title-click="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) } })"
          @comments-click="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) }, hash: '#comments' })"
          @view-recorded="onViewRecorded(post.id)"
        />
      </div>
    </template>

  </div>
</template>
