<script setup lang="ts">
import { Button } from '@/shared/ui';
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
          <DialogTitle class="text-lg font-semibold">
            {{ props.viewEvent?.title ?? 'Событие' }}
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground whitespace-pre-wrap">
            {{ props.viewEvent?.description || 'Нет описания.' }}
          </DialogDescription>
          <div class="flex justify-end pt-2">
            <Button variant="outline" type="button" @click="open = false">
              Закрыть
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
