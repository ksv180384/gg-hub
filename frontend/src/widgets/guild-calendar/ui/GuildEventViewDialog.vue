<script setup lang="ts">
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import type { GuildEvent } from '@/shared/api/eventsApi';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';

const props = defineProps<{
  viewEvent: GuildEvent | null;
}>();

const open = defineModel<boolean>('open', { required: true });

function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return 'Не указано';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return 'Не указано';
  return d.toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getEventUntilDate(event: GuildEvent | null): string {
  if (!event) return 'Не указано';
  return formatDateTime(event.recurrence_ends_at ?? event.ends_at);
}
</script>

<template>
  <DialogRoot :open="open" @update:open="(v: boolean) => (open = v)">
    <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <button
            type="button"
            class="absolute right-4 top-4 z-10 rounded-sm p-1.5 text-muted-foreground opacity-80 ring-offset-background transition-opacity hover:opacity-100 hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            aria-label="Закрыть"
            @click="open = false"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
          <DialogTitle class="pr-10 text-lg font-semibold">
            {{ props.viewEvent?.title ?? 'Событие' }}
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground whitespace-pre-wrap">
            {{ props.viewEvent?.description || 'Нет описания.' }}
          </DialogDescription>
          <div class="mt-1 grid gap-1.5 border-t border-border/50 pt-3 text-xs">
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground/80">Начало события</span>
              <span class="text-right text-muted-foreground">
                {{ formatDateTime(props.viewEvent?.starts_at) }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground/80">Проводится до</span>
              <span class="text-right text-muted-foreground">
                {{ getEventUntilDate(props.viewEvent) }}
              </span>
            </div>
          </div>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
