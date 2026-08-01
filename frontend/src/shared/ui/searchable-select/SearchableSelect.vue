<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'radix-vue';
import { X } from '@lucide/vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import Button from '@/shared/ui/button/Button.vue';
import Input from '@/shared/ui/input/Input.vue';
import { cn } from '@/shared/lib/utils';
import type { SearchableSelectOption } from './types';

const props = withDefaults(
  defineProps<{
    modelValue: string | number | null;
    options: SearchableSelectOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    clearLabel?: string;
    disabled?: boolean;
    loading?: boolean;
    ariaLabel?: string;
    class?: string;
  }>(),
  {
    placeholder: 'Выберите...',
    searchPlaceholder: 'Поиск...',
    emptyText: 'Ничего не найдено',
    disabled: false,
    loading: false,
  },
);

const emit = defineEmits<{
  (event: 'update:modelValue', value: string | number | null): void;
}>();

const open = ref(false);
const search = ref('');
const searchInputRef = ref<HTMLInputElement | null>(null);

const selectedOption = computed(() =>
  props.options.find((option) => option.value === props.modelValue),
);

const filteredOptions = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('ru-RU');

  if (!query) {
    return props.options;
  }

  return props.options.filter((option) =>
    option.label.toLocaleLowerCase('ru-RU').includes(query),
  );
});

const triggerLabel = computed(() => {
  if (selectedOption.value) {
    return selectedOption.value.label;
  }

  return props.loading ? 'Загрузка…' : props.placeholder;
});

watch(open, async (isOpen) => {
  if (!isOpen) {
    return;
  }

  search.value = '';
  await nextTick();
  searchInputRef.value?.focus({ preventScroll: true });
});

function select(value: string | number | null): void {
  emit('update:modelValue', value);
  open.value = false;
}
</script>

<template>
  <PopoverRoot v-model:open="open">
    <div class="relative w-full">
      <PopoverTrigger
        type="button"
        :disabled="disabled || loading"
        :aria-label="ariaLabel"
        :class="cn(
          'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors',
          'hover:border-primary/25 focus-visible:border-primary/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/15',
          'disabled:cursor-not-allowed disabled:bg-muted/40 disabled:opacity-70',
          modelValue != null && 'pr-9',
          props.class,
        )"
      >
        <span
          class="truncate text-left"
          :class="{ 'text-muted-foreground': modelValue == null }"
        >
          {{ triggerLabel }}
        </span>
        <svg
          class="h-4 w-4 shrink-0 text-muted-foreground"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
        </svg>
      </PopoverTrigger>
      <Button
        v-if="modelValue != null"
        variant="ghost"
        size="icon"
        class="absolute right-0.5 top-0.5 h-8 w-8"
        aria-label="Очистить выбор"
        @click.stop="select(null)"
      >
        <X />
      </Button>
    </div>
    <ClientOnly>
      <PopoverPortal>
        <PopoverContent
          align="start"
          side="bottom"
          :side-offset="4"
          class="z-50 w-[var(--radix-popover-trigger-width)] overflow-hidden rounded-lg border border-border/80 bg-popover text-popover-foreground shadow-lg shadow-black/5"
        >
          <div class="border-b border-border/80 p-1.5">
            <Input
              ref="searchInputRef"
              v-model="search"
              type="text"
              :placeholder="searchPlaceholder"
              class="h-8 text-sm"
              @keydown.stop
            />
          </div>
          <div class="max-h-60 overflow-y-auto p-1">
            <button
              v-if="clearLabel"
              type="button"
              class="flex w-full items-center rounded-md px-2.5 py-2 text-left text-sm text-muted-foreground transition-colors hover:bg-accent/70 hover:text-foreground"
              :class="{ 'bg-accent/70 text-foreground': modelValue == null }"
              @click="select(null)"
            >
              {{ clearLabel }}
            </button>
            <button
              v-for="option in filteredOptions"
              :key="String(option.value)"
              type="button"
              :disabled="option.disabled"
              class="flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left text-sm transition-colors hover:bg-accent/70 disabled:cursor-not-allowed disabled:opacity-50"
              :class="{ 'bg-accent/70': option.value === modelValue }"
              @click="select(option.value)"
            >
              <span class="truncate">{{ option.label }}</span>
              <span v-if="option.value === modelValue" aria-hidden="true">✓</span>
            </button>
            <p
              v-if="filteredOptions.length === 0"
              class="px-2 py-4 text-center text-sm text-muted-foreground"
            >
              {{ emptyText }}
            </p>
          </div>
        </PopoverContent>
      </PopoverPortal>
    </ClientOnly>
  </PopoverRoot>
</template>
