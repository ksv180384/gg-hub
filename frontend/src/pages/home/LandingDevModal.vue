<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
} from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { Button } from '@/shared/ui';

defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'update:open', value: boolean): void;
}>();

function onUpdateOpen(v: boolean) {
  emit('update:open', v);
  if (!v) emit('close');
}
</script>

<template>
  <DialogRoot :open="open" @update:open="onUpdateOpen">
    <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 pt-14 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 sm:max-w-lg"
          :aria-describedby="'landing-dev-modal-desc'"
        >
          <button
            type="button"
            class="absolute right-4 top-4 z-10 rounded-sm p-1.5 text-muted-foreground opacity-80 ring-offset-background transition-opacity hover:opacity-100 hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            aria-label="Закрыть"
            @click="$emit('close')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
          <DialogTitle class="text-lg font-semibold pr-10">Сайт в разработке</DialogTitle>
          <p id="landing-dev-modal-desc" class="mt-4 text-sm text-muted-foreground leading-relaxed">
            Мы активно работаем над платформой. Регистрация и часть функций появятся позже; каталог гильдий и разделы сайта
            уже можно открывать. Спасибо за интерес к gg-hub.
          </p>
          <div class="mt-6 flex justify-end">
            <Button type="button" @click="$emit('close')">Понятно</Button>
          </div>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>

