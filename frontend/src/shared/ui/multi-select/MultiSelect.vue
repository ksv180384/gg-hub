<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
  DropdownMenuRoot,
  DropdownMenuTrigger,
  DropdownMenuPortal,
  DropdownMenuContent as DropdownContent,
} from 'radix-vue';
import { cn } from '@/shared/lib/utils';
import type { MultiSelectOption } from './types';

const props = withDefaults(
  defineProps<{
    modelValue: (string | number)[];
    options: MultiSelectOption[];
    placeholder?: string;
    disabled?: boolean;
    searchPlaceholder?: string;
    emptyText?: string;
    triggerClass?: string;
    /** Подпись для «Выбрать все» */
    selectAllLabel?: string;
    /** Подпись для «Сбросить» */
    clearAllLabel?: string;
  }>(),
  {
    placeholder: 'Выберите...',
    searchPlaceholder: 'Поиск...',
    emptyText: 'Ничего не найдено',
    selectAllLabel: 'Выбрать все',
    clearAllLabel: 'Сбросить',
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: (string | number)[]): void;
}>();

const searchQuery = ref('');

const filteredOptions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return props.options;
  return props.options.filter((opt) =>
    opt.label.toLowerCase().includes(q)
  );
});

const selectedSet = computed(() => new Set(props.modelValue));

/** Текст на триггере: 0 — placeholder, 1 — один элемент, 2+ — первый элемент (бейдж +N отдельно). */
const triggerLabel = computed(() => {
  const len = props.modelValue.length;
  if (len === 0) return props.placeholder;
  const first = props.options.find((o) => o.value === props.modelValue[0]);
  return first?.label ?? String(props.modelValue[0]);
});

function toggle(value: string | number) {
  const set = new Set(props.modelValue);
  if (set.has(value)) {
    set.delete(value);
  } else {
    set.add(value);
  }
  emit('update:modelValue', Array.from(set));
}

function selectAllFiltered() {
  const current = new Set(props.modelValue);
  filteredOptions.value.forEach((opt) => {
    if (!opt.disabled) current.add(opt.value);
  });
  emit('update:modelValue', Array.from(current));
}

function clearAllFiltered() {
  const filteredValues = new Set(filteredOptions.value.map((o) => o.value));
  const next = props.modelValue.filter((v) => !filteredValues.has(v));
  emit('update:modelValue', next);
}

function clearAll() {
  emit('update:modelValue', []);
}

</script>

<template>
  <DropdownMenuRoot>
    <DropdownMenuTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :class="cn(
          'flex h-8 min-w-[120px] items-center justify-between gap-1 rounded-md border border-input bg-background px-3 py-1.5 text-sm font-normal shadow-sm transition-colors',
          'hover:bg-accent hover:text-accent-foreground',
          'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
          'disabled:pointer-events-none disabled:opacity-50',
          'data-[placeholder]:text-muted-foreground',
          triggerClass
        )"
      >
        <span class="flex min-w-0 items-center gap-1.5">
          <span class="truncate" :class="{ 'text-muted-foreground': modelValue.length === 0 }">
            {{ triggerLabel }}
          </span>
          <span
            v-if="modelValue.length > 1"
            class="shrink-0 rounded bg-secondary px-1.5 py-0.5 text-xs font-medium text-secondary-foreground"
          >
            +{{ modelValue.length - 1 }}
          </span>
        </span>
        <svg
          class="ml-1 h-4 w-4 shrink-0 opacity-50"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
    </DropdownMenuTrigger>
    <DropdownMenuPortal>
      <DropdownContent
        align="start"
        :side-offset="4"
        class="p-0"
        :class="cn(
          'z-50 max-h-[280px] min-w-[200px] overflow-hidden rounded-md border bg-popover p-0 text-popover-foreground shadow-md',
          'data-[state=open]:animate-in data-[state=closed]:animate-out',
          'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95'
        )"
      >
        <div class="border-b p-2">
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="flex h-8 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            @keydown.stop
          >
        </div>
        <div class="flex gap-1 border-b px-2 py-1.5 text-xs">
          <button
            type="button"
            class="text-primary hover:underline"
            @click="selectAllFiltered"
          >
            {{ selectAllLabel }}
          </button>
          <span class="text-muted-foreground">·</span>
          <button
            type="button"
            class="text-muted-foreground hover:text-foreground hover:underline"
            @click="modelValue.length ? clearAll() : null"
          >
            {{ clearAllLabel }}
          </button>
        </div>
        <div class="max-h-[220px] overflow-y-auto p-1 pb-3">
          <template v-if="filteredOptions.length === 0">
            <p class="py-4 text-center text-sm text-muted-foreground">
              {{ emptyText }}
            </p>
          </template>
          <label
            v-for="opt in filteredOptions"
            :key="String(opt.value)"
            :class="cn(
              'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent',
              opt.disabled && 'cursor-not-allowed opacity-50'
            )"
          >
            <input
              type="checkbox"
              :checked="selectedSet.has(opt.value)"
              :disabled="opt.disabled"
              class="h-4 w-4 rounded border-input"
              @change="!opt.disabled && toggle(opt.value)"
            >
            <span class="truncate">{{ opt.label }}</span>
          </label>
        </div>
      </DropdownContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>
