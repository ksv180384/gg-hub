<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, PostCardPreview } from '@/shared/ui';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { postsApi, type Post } from '@/shared/api/postsApi';
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

const canModeratePosts = computed(
  () => !!guild.value?.my_permission_slugs?.includes('publikovat-post')
);

async function loadGuildJournal() {
  if (!guildId.value) return;

  guild.value = null;
  publishedPosts.value = [];
  pendingPosts.value = [];
  showPending.value = false;
  loadingGuild.value = true;
  loadingPublished.value = true;
  loadingPending.value = false;

  try {
    // Используем настройки гильдии, чтобы получить my_permission_slugs
    guild.value = await guildsApi.getGuildForSettings(guildId.value);
  } catch {
    guild.value = null;
  } finally {
    loadingGuild.value = false;
  }

  try {
    publishedPosts.value = await postsApi.getGuildPosts(guildId.value);
  } finally {
    loadingPublished.value = false;
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

watch(guildId, () => {
  loadGuildJournal();
}, { immediate: true });

const pendingCount = computed(() => pendingPosts.value.length);

function onViewRecorded(postId: number) {
  const p = publishedPosts.value.find((x) => x.id === postId)
    ?? pendingPosts.value.find((x) => x.id === postId);
  if (p) p.views_count = (p.views_count ?? 0) + 1;
}
</script>

<template>
  <div class="container py-6 space-y-4 max-w-2xl mx-auto">
    <div
      v-if="canModeratePosts"
      class="flex justify-between"
    >
      <div>
        {{ showPending ? 'Посты, ожидающие публикации' : 'Журнал гильдии' }}
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        class="cursor-pointer"
        :class="
          showPending
            ? 'bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground'
            : ''
        "
        @click="showPending = !showPending"
      >
        Ожидают публикации
        <span
          class="ml-1 inline-flex h-5 min-w-[1.5rem] items-center justify-center rounded-full bg-background px-1 text-xs font-medium text-foreground"
        >
          {{ loadingPending ? '…' : pendingCount }}
        </span>
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
          @view-recorded="onViewRecorded(post.id)"
        />
      </div>
    </template>
    <template v-else>
      <p v-if="loadingPublished" class="text-sm text-muted-foreground">Загрузка постов…</p>
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
          @view-recorded="onViewRecorded(post.id)"
        />
      </div>
    </template>

  </div>
</template>
