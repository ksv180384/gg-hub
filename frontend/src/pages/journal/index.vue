<script setup lang="ts">
import PostCardPreview from '@/shared/ui/post/PostCardPreview.vue';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useSiteContextStore } from '@/stores/siteContext';

const router = useRouter();
const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const posts = ref<Post[]>([]);
const loading = ref(true);

async function loadJournal() {
  const g = game.value;
  if (!g?.id) {
    posts.value = [];
    loading.value = false;
    return;
  }
  loading.value = true;
  try {
    posts.value = await postsApi.getGlobalJournalPosts(g.id);
  } catch {
    posts.value = [];
  } finally {
    loading.value = false;
  }
}

watch(() => game.value?.id, () => {
  loadJournal();
}, { immediate: true });

function onViewRecorded(postId: number) {
  const p = posts.value.find((x) => x.id === postId);
  if (p) p.views_count = (p.views_count ?? 0) + 1;
}

function postLink(post: Post) {
  const guildId = post.guild_id;
  if (guildId) {
    return { name: 'guild-post-show' as const, params: { id: String(guildId), postId: String(post.id) } };
  }
  return { name: 'my-posts' as const };
}

function onCommentsClick(post: Post) {
  if (post.guild_id != null) {
    router.push({ name: 'guild-post-show', params: { id: String(post.guild_id), postId: String(post.id) }, hash: '#comments' });
  }
}
</script>

<template>
  <div class="container py-6 space-y-4 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold tracking-tight">Журнал</h1>

    <p v-if="!game?.id" class="text-sm text-muted-foreground">
      Выберите игру для просмотра журнала.
    </p>
    <template v-else>
      <p v-if="loading" class="text-sm text-muted-foreground">Загрузка постов…</p>
      <p v-else-if="posts.length === 0" class="text-sm text-muted-foreground">
        В журнале пока нет постов.
      </p>
      <div v-else class="space-y-4">
        <PostCardPreview
          v-for="post in posts"
          :key="post.id"
          :post="post"
          :guild-id="post.guild_id ?? undefined"
          date-type="global"
          @title-click="router.push(postLink(post))"
          @comments-click="onCommentsClick(post)"
          @view-recorded="onViewRecorded(post.id)"
        />
      </div>
    </template>
  </div>
</template>
