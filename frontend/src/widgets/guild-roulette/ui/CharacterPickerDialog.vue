<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import { Avatar, Badge, Button } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { avatarFallback } from '@/features/guild-roulette';
import type { GuildRosterMember } from '@/shared/api/guildsApi';

defineProps<{
  open: boolean;
  characters: GuildRosterMember[];
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'pick', characterId: number): void;
}>();

function onOpenChange(value: boolean) {
  emit('update:open', value);
}

function pick(characterId: number) {
  emit('pick', characterId);
}
</script>

<template>
  <DialogRoot :open="open" @update:open="onOpenChange">
    <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            Выберите персонажа для рулетки
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            У вас несколько персонажей в гильдии — можно отправить одного или нескольких,
            добавляя их по одному.
          </DialogDescription>
          <ul class="space-y-2 max-h-[60vh] overflow-y-auto">
            <li
              v-for="character in characters"
              :key="character.character_id"
              class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
            >
              <div class="flex min-w-0 flex-1 items-center gap-3">
                <Avatar
                  :src="character.avatar_url ?? undefined"
                  :alt="character.name"
                  :fallback="avatarFallback(character.name)"
                  class="h-9 w-9 shrink-0"
                />
                <div class="min-w-0">
                  <p class="truncate text-base font-medium">{{ character.name }}</p>
                  <Badge
                    v-if="character.guild_role"
                    variant="secondary"
                    class="mt-0.5 text-xs"
                  >
                    {{ character.guild_role.name }}
                  </Badge>
                </div>
              </div>
              <Button
                type="button"
                size="sm"
                variant="default"
                class="shrink-0"
                @click="pick(character.character_id)"
              >
                Участвовать
              </Button>
            </li>
          </ul>
          <div class="flex justify-end gap-2 pt-2">
            <Button variant="outline" @click="onOpenChange(false)">Закрыть</Button>
          </div>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
