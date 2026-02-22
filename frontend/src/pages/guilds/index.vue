<script setup lang="ts">
import { Button, Input } from '@/shared/ui';
import GuildCard from './GuildCard.vue';
import { useSiteContextStore } from '@/stores/siteContext';
import { useAuthStore } from '@/stores/auth';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';

const siteContext = useSiteContextStore();
const authStore = useAuthStore();
const router = useRouter();
const search = ref('');
const guilds = ref<Guild[]>([]);
const memberGuildIds = ref<Set<number>>(new Set());
const loading = ref(true);
const error = ref<string | null>(null);

const isMemberOfGuild = (guildId: number) => memberGuildIds.value.has(guildId);
const canAccessSettings = (g: Guild) =>
  authStore.user && (g.owner_id === authStore.user!.id || isMemberOfGuild(g.id));

const filteredGuilds = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return guilds.value;
  return guilds.value.filter(
    (g) =>
      g.name.toLowerCase().includes(q) ||
      g.game?.name?.toLowerCase().includes(q)
  );
});

async function loadMemberGuildIds() {
  const game = siteContext.game;
  if (!authStore.isAuthenticated || !game?.id) {
    memberGuildIds.value = new Set();
    return;
  }
  try {
    const list = await guildsApi.getMyGuildsForGame(game.id);
    memberGuildIds.value = new Set(list.map((g) => g.id));
  } catch {
    memberGuildIds.value = new Set();
  }
}

async function loadGuilds() {
  loading.value = true;
  error.value = null;
  try {
    const params: { per_page: number; game_id?: number } = { per_page: 50 };
    if (siteContext.game?.id) params.game_id = siteContext.game.id;
    const { guilds: list } = await guildsApi.getGuilds(params);
    guilds.value = list;
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить гильдии';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadGuilds();
  loadMemberGuildIds();
});
watch(() => siteContext.game?.id, () => {
  loadMemberGuildIds();
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-5xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Гильдии</h1>
      <p class="mb-8 text-muted-foreground">
        <template v-if="siteContext.game">
          Гильдии игры {{ siteContext.game.name }}. Найдите гильдию или создайте свою.
        </template>
        <template v-else>
          Найдите гильдию под свой стиль игры или создайте свою.
        </template>
      </p>

      <div class="mb-8 flex flex-wrap items-center gap-4">
        <Input
          v-model="search"
          placeholder="Поиск по названию или игре..."
          class="max-w-md"
        />
        <Button v-if="authStore.isAuthenticated" @click="router.push({ name: 'guilds-create' })">
          Создать гильдию
        </Button>
      </div>

      <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ error }}
      </div>
      <div v-if="loading" class="text-muted-foreground">Загрузка...</div>

      <div v-else class="grid justify-items-center gap-6 sm:grid-cols-2 sm:justify-items-stretch lg:grid-cols-3">
        <GuildCard
          v-for="(g, i) in filteredGuilds"
          :key="g.id"
          :guild="g"
          list-mode
          :can-access-settings="canAccessSettings(g)"
          class="animate-in fade-in slide-in-from-bottom-3"
          :style="{ animationDelay: `${i * 80}ms`, animationDuration: '400ms', animationFillMode: 'backwards' }"
        />
      </div>

      <div v-if="!loading && filteredGuilds.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Гильдий пока нет.
        <template v-if="authStore.isAuthenticated"> Создайте первую.</template>
      </div>
    </div>
  </div>
</template>
