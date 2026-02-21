<script setup lang="ts">
import { Avatar, Button, Tooltip } from '@/shared/ui';
import type { Character } from '@/shared/api/charactersApi';
import CharacterClassBadge from './CharacterClassBadge.vue';

defineProps<{
  character: Character;
}>();

const emit = defineEmits<{ (e: 'edit'): void }>();
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
      <p class="text-sm text-muted-foreground">
        <span v-if="character.localization?.name" :title="'Локализация'">{{ character.localization.name }}</span>
        <template v-if="character.localization?.name && character.server?.name"> · </template>
        <span v-if="character.server?.name" :title="'Сервер'">{{ character.server.name }}</span>
        <template v-if="!character.localization?.name && !character.server?.name">—</template>
      </p>
      <div v-if="character.game_classes?.length" class="mt-1 flex flex-wrap items-center gap-1.5">
        <CharacterClassBadge
          v-for="gc in character.game_classes"
          :key="gc.id"
          :game-class="gc"
        />
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
