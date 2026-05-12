<script setup lang="ts">
import { reactive } from 'vue';
import { GameCatalogCard } from '@/entities/game';
import { useGamesCatalog } from '@/features/games-catalog';

const model = reactive(useGamesCatalog());
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Игры</h1>
      <p class="mb-8 text-muted-foreground">
        Выберите игру — откроется её сайт.
      </p>

      <div
        v-if="model.error"
        class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive"
        role="alert"
      >
        {{ model.error }}
      </div>

      <p v-if="model.loading" class="text-muted-foreground">Загрузка...</p>

      <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <GameCatalogCard
          v-for="(game, index) in model.games"
          :key="game.id"
          :game="game"
          :animation-delay-ms="index * 80"
        />
      </div>

      <div
        v-if="!model.loading && model.games.length === 0"
        class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
      >
        Игр пока нет.
      </div>
    </div>
  </div>
</template>
