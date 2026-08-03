<script setup lang="ts">
import {
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'radix-vue';
import { X } from '@lucide/vue';
import { Button } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import type { Character } from '@/shared/api/charactersApi';

defineProps<{
  open: boolean;
  query: string;
  message: string;
  candidates: Character[];
  searching: boolean;
  invitingCharacterId: number | null;
  serverName?: string;
}>();

const emit = defineEmits<{
  (event: 'update:open', value: boolean): void;
  (event: 'update:query', value: string): void;
  (event: 'update:message', value: string): void;
  (event: 'invite', character: Character): void;
}>();
</script>

<template>
  <DialogRoot
    :open="open"
    @update:open="emit('update:open', $event)"
  >
    <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 shadow-lg data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
        >
          <div class="pr-10">
            <DialogTitle class="text-lg font-semibold">
              Пригласить персонажа
            </DialogTitle>
            <DialogDescription class="sr-only">
              Поиск персонажа и отправка приглашения в конст пати
            </DialogDescription>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            class="absolute right-4 top-4"
            title="Закрыть"
            aria-label="Закрыть"
            @click="emit('update:open', false)"
          >
            <X class="size-4" />
          </Button>

          <div class="mt-5 space-y-4">
            <input
              :value="query"
              class="h-10 w-full rounded-md border bg-background px-3 text-sm"
              placeholder="Ник персонажа"
              autofocus
              @input="emit('update:query', ($event.target as HTMLInputElement).value)"
            />

            <div class="max-h-64 overflow-y-auto">
              <div
                v-if="searching"
                class="py-8 text-center text-sm text-muted-foreground"
              >
                Поиск...
              </div>
              <div
                v-else-if="query.trim().length < 2"
                class="py-8 text-center text-sm text-muted-foreground"
              >
                Введите минимум два символа.
              </div>
              <div
                v-else-if="candidates.length === 0"
                class="py-8 text-center text-sm text-muted-foreground"
              >
                Персонажи не найдены.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="candidate in candidates"
                  :key="candidate.id"
                  class="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                >
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium">
                      {{ candidate.name }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                      {{ candidate.server?.name ?? serverName }}
                    </p>
                  </div>
                  <Button
                    type="button"
                    size="sm"
                    class="shrink-0"
                    :disabled="invitingCharacterId === candidate.id"
                    @click="emit('invite', candidate)"
                  >
                    Пригласить
                  </Button>
                </div>
              </div>
            </div>

            <textarea
              :value="message"
              class="min-h-20 w-full resize-y rounded-md border bg-background px-3 py-2 text-sm"
              placeholder="Сообщение"
              @input="emit('update:message', ($event.target as HTMLTextAreaElement).value)"
            />
          </div>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
