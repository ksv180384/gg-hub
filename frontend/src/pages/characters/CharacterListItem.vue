<script setup lang="ts">
import { Avatar, Button, Tooltip } from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import type { Character } from '@/shared/api/charactersApi';

defineProps<{
  character: Character;
}>();

const emit = defineEmits<{ (e: 'edit'): void }>();

function locationString(c: Character): string {
  const parts: string[] = [];
  if (c.localization?.name) parts.push(c.localization.name);
  if (c.server?.name) parts.push(c.server.name);
  return parts.join(' · ') || '—';
}
</script>

<template>
  <li
    class="flex flex-wrap items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50 sm:gap-4"
  >
    <Avatar
      :src="character.avatar_url ?? undefined"
      :alt="character.name"
      :fallback="character.name.slice(0, 2).toUpperCase()"
      class="h-12 w-12 shrink-0"
    />
    <div class="min-w-0 flex-1">
      <p class="font-medium">{{ character.name }}</p>
      <p class="text-sm text-muted-foreground">{{ locationString(character) }}</p>
      <div v-if="character.game_classes?.length" class="mt-1 flex flex-wrap items-center gap-1.5">
        <template v-for="gc in character.game_classes" :key="gc.id">
          <img
            v-if="gc.image_thumb || gc.image"
            :src="storageImageUrl(gc.image_thumb || gc.image || '')"
            :alt="gc.name_ru || gc.name"
            :title="gc.name_ru || gc.name"
            class="h-6 w-6 rounded object-cover"
          />
          <span v-else class="text-xs text-muted-foreground">{{ gc.name_ru || gc.name }}</span>
        </template>
      </div>
    </div>
    <Tooltip content="Редактировать">
      <Button
        type="button"
        size="icon"
        variant="ghost"
        class="h-9 w-9 shrink-0 min-h-9 min-w-9 touch-manipulation"
        aria-label="Редактировать"
        @click="emit('edit')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden>
          <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
          <path d="m15 5 4 4"/>
        </svg>
      </Button>
    </Tooltip>
  </li>
</template>
