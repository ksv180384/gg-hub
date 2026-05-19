<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'radix-vue';
import { Input } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { getTimezones, getTimezoneLabel } from '@/shared/lib/timezones';
import { cn } from '@/shared/lib/utils';

interface Props {
  modelValue?: string;
  class?: string;
  id?: string;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: 'UTC',
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
const search = ref('');
const searchInputRef = ref<HTMLInputElement | null>(null);

const timezones = getTimezones();

const filteredTimezones = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return timezones;
  return timezones.filter(
    (tz) =>
      tz.toLowerCase().includes(q) ||
      getTimezoneLabel(tz).toLowerCase().includes(q)
  );
});

const displayLabel = computed(() =>
  props.modelValue ? getTimezoneLabel(props.modelValue) : 'Выберите часовой пояс'
);

watch(open, (isOpen) => {
  if (isOpen) {
    search.value = '';
    setTimeout(() => searchInputRef.value?.focus(), 0);
  }
});

function select(tz: string) {
  emit('update:modelValue', tz);
  open.value = false;
}
</script>

<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger
      :id="id"
      :class="cn(
        'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-border bg-muted/25 px-3 py-2 text-sm text-foreground shadow-none transition-colors duration-150 placeholder:text-muted-foreground hover:border-primary/25 hover:bg-background focus:outline-none focus:border-primary/45 focus:bg-background focus:ring-2 focus:ring-primary/15 disabled:cursor-not-allowed disabled:bg-muted/40 disabled:opacity-70 [&>span]:line-clamp-1',
        props.class
      )"
      type="button"
    >
      <span class="truncate text-left">{{ displayLabel }}</span>
      <span class="ml-2 shrink-0 text-muted-foreground" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m6 9 6 6 6-6" />
        </svg>
      </span>
    </PopoverTrigger>
    <ClientOnly>
    <PopoverPortal>
      <PopoverContent
        side="bottom"
        align="start"
        :side-offset="4"
        :class="cn(
          'z-50 w-[var(--radix-popover-trigger-width)] max-h-[min(20rem,var(--radix-popover-content-available-height))] overflow-hidden rounded-lg border border-border/80 bg-popover text-popover-foreground shadow-lg shadow-black/5',
          'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
          'data-[side=bottom]:slide-in-from-top-2'
        )"
      >
        <div class="border-b border-border/80 p-1.5">
          <Input
            ref="searchInputRef"
            v-model="search"
            type="text"
            placeholder="Поиск..."
            class="h-8 text-sm"
            @keydown.stop
          />
        </div>
        <div class="max-h-[14rem] overflow-y-auto p-1">
          <button
            v-for="tz in filteredTimezones"
            :key="tz"
            type="button"
            :class="cn(
              'relative flex w-full cursor-default select-none items-center rounded-md px-2.5 py-2 text-sm outline-none transition-colors hover:bg-accent/70 hover:text-foreground',
              modelValue === tz && 'bg-accent/70 text-foreground'
            )"
            @click="select(tz)"
          >
            {{ getTimezoneLabel(tz) }}
          </button>
          <p v-if="filteredTimezones.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
            Ничего не найдено
          </p>
        </div>
      </PopoverContent>
    </PopoverPortal>
    </ClientOnly>
  </PopoverRoot>
</template>
