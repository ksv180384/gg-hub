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

const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    loading?: boolean;
    confirmVariant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  }>(),
  {
    confirmLabel: 'Подтвердить',
    cancelLabel: 'Отмена',
    loading: false,
    confirmVariant: 'destructive',
  }
);

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'confirm'): void;
}>();

function onOpenChange(value: boolean) {
  emit('update:open', value);
}

function close() {
  emit('update:open', false);
}
</script>

<template>
  <DialogRoot :open="open" @update:open="onOpenChange">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <DialogContent
        class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
        :aria-describedby="undefined"
      >
        <DialogTitle class="text-lg font-semibold">
          {{ title }}
        </DialogTitle>
        <DialogDescription v-if="description || $slots.description" class="text-sm text-muted-foreground">
          <slot name="description">
            {{ description }}
          </slot>
        </DialogDescription>
        <div class="flex justify-end gap-2 pt-4">
          <Button variant="outline" :disabled="loading" @click="close">
            {{ cancelLabel }}
          </Button>
          <Button
            :variant="confirmVariant"
            :disabled="loading"
            @click="emit('confirm')"
          >
            {{ loading ? '…' : confirmLabel }}
          </Button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
