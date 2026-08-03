<script setup lang="ts">
import { Button } from '@/shared/ui';

defineProps<{
  open: boolean;
  raidName: string;
  characters: { id: number; name: string; avatar_url?: string | null }[];
  submitting?: boolean;
  error?: string | null;
}>();

const emit = defineEmits<{
  (event: 'close'): void;
  (event: 'select', characterId: number): void;
}>();
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <button
        type="button"
        class="absolute inset-0 bg-black/70"
        aria-label="Закрыть"
        @click="emit('close')"
      />
      <section class="relative z-10 w-full max-w-md rounded-xl border bg-background p-5 shadow-xl">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">Выберите персонажа</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Заявка в рейд «{{ raidName }}» будет подана от выбранного персонажа.
            </p>
          </div>
          <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="emit('close')">
            ×
          </Button>
        </div>
        <p v-if="error" class="mt-4 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
          {{ error }}
        </p>

        <div class="mt-4 space-y-2">
          <button
            v-for="character in characters"
            :key="character.id"
            type="button"
            class="flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-accent"
            :disabled="submitting"
            @click="emit('select', character.id)"
          >
            <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-muted font-medium">
              <img
                v-if="character.avatar_url"
                :src="character.avatar_url"
                :alt="character.name"
                class="h-full w-full object-cover"
              />
              <span v-else>{{ character.name.slice(0, 2).toUpperCase() }}</span>
            </span>
            <span class="font-medium">{{ character.name }}</span>
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
