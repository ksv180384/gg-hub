<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Badge, Button, Avatar, Input } from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { useAuthStore } from '@/stores/auth';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const siteContext = useSiteContextStore();
const authStore = useAuthStore();
const router = useRouter();
const search = ref('');
const guilds = ref<Guild[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const filteredGuilds = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return guilds.value;
  return guilds.value.filter(
    (g) =>
      g.name.toLowerCase().includes(q) ||
      g.game?.name?.toLowerCase().includes(q)
  );
});

function getTag(name: string): string {
  return name
    .split(/\s+/)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

async function loadGuilds() {
  loading.value = true;
  error.value = null;
  try {
    const { guilds: list } = await guildsApi.getGuilds({ per_page: 50 });
    guilds.value = list;
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить гильдии';
  } finally {
    loading.value = false;
  }
}

onMounted(() => loadGuilds());
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
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

      <div v-else class="grid gap-6 sm:grid-cols-2">
        <Card
          v-for="(g, i) in filteredGuilds"
          :key="g.id"
          class="transition-all hover:shadow-md animate-in fade-in slide-in-from-bottom-3"
          :style="{ animationDelay: `${i * 80}ms`, animationDuration: '400ms', animationFillMode: 'backwards' }"
        >
          <CardHeader class="flex flex-row items-center gap-4 space-y-0 pb-2">
            <Avatar :fallback="getTag(g.name)" class="h-12 w-12 rounded-lg" />
            <div class="flex-1 space-y-1 min-w-0">
              <CardTitle class="text-lg">{{ g.name }}</CardTitle>
              <p class="text-sm text-muted-foreground truncate">
                {{ g.game?.name ?? '—' }}
                <template v-if="g.localization"> · {{ g.localization.name }}</template>
              </p>
            </div>
            <Badge v-if="g.is_recruiting" variant="outline">Набор</Badge>
          </CardHeader>
          <CardContent class="flex gap-2">
            <Button
              v-if="authStore.user && g.owner_id === authStore.user.id"
              variant="outline"
              size="sm"
              class="flex-1"
              @click="router.push({ name: 'guild-settings', params: { id: String(g.id) } })"
            >
              Настройки
            </Button>
            <Button variant="outline" size="sm" class="flex-1">Подробнее</Button>
          </CardContent>
        </Card>
      </div>

      <div v-if="!loading && filteredGuilds.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Гильдий пока нет.
        <template v-if="authStore.isAuthenticated"> Создайте первую.</template>
      </div>
    </div>
  </div>
</template>
