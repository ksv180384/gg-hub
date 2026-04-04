<script setup lang="ts">
import PostCardPreview from '@/shared/ui/post/PostCardPreview.vue';
import { Button, Select } from '@/shared/ui';
import type { SelectOption } from '@/shared/ui';
import { postsApi, type Post } from '@/shared/api/postsApi';
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const posts = ref<Post[]>([]);
const loading = ref(true);
const pendingGlobalCount = ref(0);
const guilds = ref<{ id: number; name: string }[]>([]);
const games = ref<{ id: number; name: string }[]>([]);
type FilterMode = 'all' | 'pending_global';
type ScopeMode = 'all' | 'global' | 'guild';
const filter = ref<FilterMode>('all');
const scope = ref<ScopeMode>('all');
/** Radix Select не допускает value="", используем специальные значения. */
const GUILD_ALL = '__all_guilds__';
const GAME_ALL = '__all_games__';
const STATUS_ALL = '__all_statuses__';
const guildId = ref<string>(GUILD_ALL);
const gameId = ref<string>(GAME_ALL);
const statusFilter = ref<string>(STATUS_ALL);

const statusOptions: SelectOption[] = [
  { value: STATUS_ALL, label: 'Все статусы' },
  { value: 'pending', label: 'На модерации' },
  { value: 'published', label: 'Опубликован' },
  { value: 'draft', label: 'Черновик' },
  { value: 'hidden', label: 'Скрыт' },
  { value: 'rejected', label: 'Отклонён' },
  { value: 'blocked', label: 'Заблокирован' },
];

const guildOptions = computed<SelectOption[]>(() => [
  { value: GUILD_ALL, label: 'Все гильдии' },
  ...guilds.value.map((g) => ({ value: String(g.id), label: g.name })),
]);

const gameOptions = computed<SelectOption[]>(() => [
  { value: GAME_ALL, label: 'Все игры' },
  ...games.value.map((g) => ({ value: String(g.id), label: g.name })),
]);

async function loadPosts() {
  loading.value = true;
  try {
    const opts: { filter?: 'pending_global'; scope?: 'global' | 'guild'; guildId?: number; gameId?: number; status?: string } = {};
    if (filter.value === 'pending_global') opts.filter = 'pending_global';
    if (scope.value === 'global') opts.scope = 'global';
    if (scope.value === 'guild') {
      opts.scope = 'guild';
      if (guildId.value && guildId.value !== GUILD_ALL) {
        opts.guildId = Number(guildId.value);
      }
    }
    if (gameId.value && gameId.value !== GAME_ALL) {
      opts.gameId = Number(gameId.value);
    }
    if ((scope.value === 'global' || scope.value === 'guild') && statusFilter.value && statusFilter.value !== STATUS_ALL) {
      opts.status = statusFilter.value;
    }
    const { posts: data, pendingGlobalCount: count, guilds: g, games: gm } = await postsApi.getAdminPosts(opts);
    posts.value = data;
    pendingGlobalCount.value = count;
    guilds.value = g;
    games.value = gm;
  } catch {
    posts.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(loadPosts);
watch([filter, scope, guildId, gameId, statusFilter], loadPosts);

function onViewRecorded(postId: number) {
  const p = posts.value.find((x) => x.id === postId);
  if (p) p.views_count = (p.views_count ?? 0) + 1;
}

function postLink(post: Post) {
  return { name: 'admin-post-show' as const, params: { id: String(post.id) } };
}

function onAuthorClick(userId: number) {
  router.push({ name: 'admin-users-show', params: { id: String(userId) } });
}

function onCommentsClick(post: Post) {
  if (post.guild_id != null) {
    router.push({
      name: 'guild-post-show',
      params: { id: String(post.guild_id), postId: String(post.id) },
      hash: '#comments',
    });
  }
}
</script>

<template>
  <div class="container py-6 space-y-4 max-w-2xl mx-auto">
    <div class="flex flex-nowrap items-center gap-2 overflow-x-auto py-1 -mx-1">
      <div class="flex flex-nowrap items-center gap-1.5 shrink-0">
        <Button
          :variant="filter === 'all' && scope === 'all' ? 'default' : 'outline'"
          size="sm"
          class="h-8 px-2 text-xs"
          @click="(filter = 'all'), (scope = 'all')"
        >
          Все
        </Button>
        <Button
          :variant="filter === 'pending_global' ? 'default' : 'outline'"
          size="sm"
          class="h-8 px-2 text-xs"
          @click="filter = 'pending_global'"
        >
          Ожидают
          <span v-if="pendingGlobalCount > 0">({{ pendingGlobalCount }})</span>
        </Button>
        <Button
          :variant="scope === 'global' && filter === 'all' ? 'default' : 'outline'"
          size="sm"
          class="h-8 px-2 text-xs"
          @click="(scope = 'global'), (filter = 'all')"
        >
          Общие
        </Button>
        <Button
          :variant="scope === 'guild' && filter === 'all' ? 'default' : 'outline'"
          size="sm"
          class="h-8 px-2 text-xs"
          @click="(scope = 'guild'), (filter = 'all')"
        >
          Гильдии
        </Button>
        <Select
          v-if="scope === 'guild'"
          v-model="guildId"
          :options="guildOptions"
          placeholder="Гильдия"
          trigger-class="h-8 min-w-[120px] text-xs"
          content-class="z-[100]"
        />
        <Select
          v-if="scope === 'global' || scope === 'guild'"
          v-model="statusFilter"
          :options="statusOptions"
          :placeholder="scope === 'global' ? 'Статус (общий)' : 'Статус (гильдия)'"
          trigger-class="h-8 min-w-[140px] text-xs"
          content-class="z-[100]"
        />
        <Select
          v-model="gameId"
          :options="gameOptions"
          placeholder="Игра"
          trigger-class="h-8 min-w-[120px] text-xs"
          content-class="z-[100]"
        />
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Загрузка постов…</p>
    <p v-else-if="posts.length === 0" class="text-sm text-muted-foreground">
      Постов пока нет.
    </p>
    <div v-else class="space-y-4">
      <PostCardPreview
        v-for="post in posts"
        :key="post.id"
        :post="post"
        :guild-id="post.guild_id ?? undefined"
        :author-user-id="post.user_id ?? undefined"
        :show-game="true"
        :show-status="true"
        date-type="global"
        @title-click="router.push(postLink(post))"
        @author-click="onAuthorClick"
        @comments-click="onCommentsClick(post)"
        @view-recorded="onViewRecorded(post.id)"
      />
    </div>
  </div>
</template>
