<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import { Button } from '@/shared/ui';
import type { GameClass } from '@/shared/api/gamesApi';

defineProps<{
  open: boolean;
  classToDelete: GameClass | null;
  loading: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'confirm'): void;
}>();
</script>

<template>
  <DialogRoot :open="open" @update:open="emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <DialogContent
        class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
      >
        <DialogTitle class="text-lg font-semibold">Удалить класс?</DialogTitle>
        <DialogDescription class="text-sm text-muted-foreground">
          Класс «{{ classToDelete?.name_ru || classToDelete?.name }}» будет удалён. Это действие нельзя отменить.
        </DialogDescription>
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="emit('update:open', false)">Отмена</Button>
          <Button variant="destructive" :disabled="loading" @click="emit('confirm')">
            {{ loading ? 'Удаление...' : 'Удалить' }}
          </Button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
