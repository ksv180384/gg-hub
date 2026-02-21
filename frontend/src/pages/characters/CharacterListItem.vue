<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { Avatar, Button, Tooltip } from '@/shared/ui';
import type { Character } from '@/shared/api/charactersApi';
import CharacterClassBadge from './CharacterClassBadge.vue';

defineProps<{
  character: Character;
  deleting?: boolean;
}>();

const emit = defineEmits<{ (e: 'edit'): void; (e: 'delete'): void }>();
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
      <p class="flex flex-wrap items-center gap-2 font-medium">
        <span>{{ character.name }}</span>
        <Tooltip v-if="character.is_main" content="Основной">
          <span class="inline-flex shrink-0 text-amber-500" aria-label="Основной">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
              <path d="M2 17l2-7 4 3 4-6 4 6 4-3 2 7H2zm0 2h20v2H2v-2z" />
            </svg>
          </span>
        </Tooltip>
      </p>
      <p class="text-sm text-muted-foreground">
        <span v-if="character.localization?.name" :title="'Локализация'">{{ character.localization.name }}</span>
        <template v-if="character.localization?.name && character.server?.name"> · </template>
        <span v-if="character.server?.name" :title="'Сервер'">{{ character.server.name }}</span>
        <template v-if="!character.localization?.name && !character.server?.name">—</template>
      </p>
      <p v-if="character.guild" class="text-sm text-muted-foreground">
        <RouterLink
          :to="{ name: 'guild-show', params: { id: character.guild.id } }"
          class="text-primary hover:underline"
        >
          Гильдия: {{ character.guild.name }}
        </RouterLink>
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
    <Tooltip content="Удалить персонажа">
      <Button
        type="button"
        size="icon"
        variant="ghost"
        class="h-9 w-9 shrink-0 min-h-9 min-w-9 touch-manipulation text-destructive hover:text-destructive hover:bg-destructive/10"
        aria-label="Удалить персонажа"
        :disabled="deleting"
        @click="emit('delete')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden>
          <path d="M3 6h18"/>
          <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
          <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
          <line x1="10" x2="10" y1="11" y2="17"/>
          <line x1="14" x2="14" y1="11" y2="17"/>
        </svg>
      </Button>
    </Tooltip>
  </li>
</template>
