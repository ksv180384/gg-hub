<script setup lang="ts">
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import type { GameClass } from '@/shared/api/gamesApi';

defineProps<{
  gameClass: GameClass;
}>();
</script>

<template>
  <span class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
    <img
      v-if="gameClass.image_thumb || gameClass.image"
      :src="storageImageUrl(gameClass.image_thumb || gameClass.image || '')"
      :alt="gameClass.name_ru || gameClass.name"
      :title="gameClass.name_ru || gameClass.name"
      class="h-6 w-6 shrink-0 rounded object-cover"
    />
    <span>{{ gameClass.name_ru || gameClass.name }}</span>
  </span>
</template>
