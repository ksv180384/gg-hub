<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardHeader, CardTitle } from '@/shared/ui';
import type { GameCatalogItem } from '@/shared/api/gamesApi';
import { getGameSiteUrl } from '@/shared/lib/gameSiteUrl';

const props = defineProps<{
  game: GameCatalogItem;
  animationDelayMs?: number;
}>();

const gameUrl = computed(() => getGameSiteUrl(props.game.slug));

function initials(name: string): string {
  return name.slice(0, 2).toUpperCase();
}
</script>

<template>
  <a
    :href="gameUrl"
    class="group block rounded-xl text-card-foreground no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
  >
    <Card
      class="overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 animate-in fade-in slide-in-from-bottom-3"
      :style="{
        animationDelay: animationDelayMs != null ? `${animationDelayMs}ms` : undefined,
        animationDuration: '400ms',
        animationFillMode: 'backwards',
      }"
    >
    <div
      class="relative aspect-[16/10] w-full overflow-hidden bg-muted"
    >
      <img
        v-if="game.image_preview"
        :src="game.image_preview"
        :alt="game.name"
        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
      />
      <div
        v-else
        class="flex h-full w-full items-center justify-center bg-gradient-to-br from-muted to-muted/70 text-4xl font-bold text-muted-foreground/60"
      >
        {{ initials(game.name) }}
      </div>
    </div>
    <CardHeader class="space-y-2 pb-2">
      <CardTitle class="line-clamp-1 text-lg leading-tight">{{ game.name }}</CardTitle>
    </CardHeader>
    </Card>
  </a>
</template>
