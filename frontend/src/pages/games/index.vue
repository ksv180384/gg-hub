<script setup lang="ts">
import { Card, CardHeader, CardTitle } from '@/shared/ui';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import { getGameSiteUrl } from '@/shared/lib/gameSiteUrl';
import { ref, onMounted } from 'vue';

const games = ref<Game[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

async function loadGames() {
  loading.value = true;
  error.value = null;
  try {
    games.value = await gamesApi.getGames();
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить игры';
  } finally {
    loading.value = false;
  }
}

function goToGameSite(g: Game) {
  window.location.href = getGameSiteUrl(g.slug);
}

onMounted(() => loadGames());
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Игры</h1>
      <p class="mb-8 text-muted-foreground">
        Выберите игру — откроется её сайт.
      </p>

      <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ error }}
      </div>

      <div v-if="loading" class="text-muted-foreground">Загрузка...</div>

      <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <Card
          v-for="(g, i) in games"
          :key="g.id"
          class="group cursor-pointer overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 animate-in fade-in slide-in-from-bottom-3"
          :style="{
            animationDelay: `${i * 80}ms`,
            animationDuration: '400ms',
            animationFillMode: 'backwards',
          }"
          @click="goToGameSite(g)"
        >
          <div class="relative aspect-[16/10] w-full overflow-hidden bg-muted">
            <img
              v-if="g.image_preview"
              :src="g.image_preview"
              :alt="g.name"
              class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <div
              v-else
              class="flex h-full w-full items-center justify-center bg-gradient-to-br from-muted to-muted/70 text-4xl font-bold text-muted-foreground/60"
            >
              {{ g.name.slice(0, 2).toUpperCase() }}
            </div>
          </div>
          <CardHeader class="space-y-2 pb-2">
            <CardTitle class="line-clamp-1 text-lg leading-tight">{{ g.name }}</CardTitle>
            <p class="line-clamp-2 text-sm text-muted-foreground">{{ g.description || '—' }}</p>
          </CardHeader>
        </Card>
      </div>

      <div v-if="!loading && games.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Игр пока нет.
      </div>
    </div>
  </div>
</template>
