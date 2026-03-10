<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, PostCard } from '@/shared/ui';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { ref, onMounted, computed } from 'vue';
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

onMounted(async () => {
  if (!guildId.value) return;

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
});

const pendingCount = computed(() => pendingPosts.value.length);
</script>

<template>
  <div class="container py-6 space-y-4">
    <div
      v-if="canModeratePosts"
      class="flex justify-end"
    >
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

    <Card>
      <CardHeader>
        <CardTitle class="text-base">
          {{ showPending ? 'Посты, ожидающие публикации' : 'Журнал гильдии' }}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <template v-if="showPending">
          <p v-if="loadingPending" class="text-sm text-muted-foreground">
            Загрузка постов на модерации…
          </p>
          <p v-else-if="pendingPosts.length === 0" class="text-sm text-muted-foreground">
            Нет постов, ожидающих публикации.
          </p>
          <div v-else class="space-y-4">
            <PostCard
              v-for="post in pendingPosts"
              :key="post.id"
              :post="post"
              date-type="guild"
              @titleClick="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) } })"
            />
          </div>
        </template>

        <template v-else>
          <p v-if="loadingPublished" class="text-sm text-muted-foreground">Загрузка постов…</p>
          <p v-else-if="publishedPosts.length === 0" class="text-sm text-muted-foreground">
            В журнале гильдии пока нет постов.
          </p>
          <div v-else class="space-y-4">
            <PostCard
              v-for="post in publishedPosts"
              :key="post.id"
              :post="post"
              date-type="guild"
              @titleClick="router.push({ name: 'guild-post-show', params: { id: String(guildId), postId: String(post.id) } })"
            />
          </div>
        </template>
      </CardContent>
    </Card>
  </div>
</template>
