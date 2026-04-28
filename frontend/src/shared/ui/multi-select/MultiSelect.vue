<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import {
  DropdownMenuRoot,
  DropdownMenuTrigger,
  DropdownMenuPortal,
  DropdownMenuContent as DropdownContent,
} from 'radix-vue';
import { cn } from '@/shared/lib/utils';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { Tooltip } from '@/shared/ui/tooltip';
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
    /**
     * 'text' — как было (лейбл +N).
     * 'badges' — показывать выбранные элементы «чипами» (без Badge), подходит для тегов.
     */
    displayMode?: 'text' | 'badges';
    /** Подпись для «Выбрать все» */
    selectAllLabel?: string;
    /** Подпись для «Сбросить» */
    clearAllLabel?: string;
    /** Максимум выбранных значений (включительно). Без пропа — без ограничения. */
    maxSelected?: number;
    /** Скрыть actions «Выбрать все · Сбросить» в меню. */
    hideActions?: boolean;
  }>(),
  {
    placeholder: 'Выберите...',
    searchPlaceholder: 'Поиск...',
    emptyText: 'Ничего не найдено',
    displayMode: 'text',
    selectAllLabel: 'Выбрать все',
    clearAllLabel: 'Сбросить',
    hideActions: false,
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: (string | number)[]): void;
}>();

const searchQuery = ref('');
const menuOpen = ref(false);
const searchInputRef = ref<HTMLInputElement | null>(null);

watch(menuOpen, async (open) => {
  if (!open) return;
  await nextTick();
  searchInputRef.value?.focus({ preventScroll: true });
});

const filteredOptions = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return props.options;
  return props.options.filter((opt) =>
    opt.label.toLowerCase().includes(q)
  );
});

const selectedSet = computed(() => new Set(props.modelValue));

const maxSelectedLimit = computed(() => {
  const m = props.maxSelected;
  if (m == null || Number.isNaN(m)) return null;
  return Math.max(1, Math.floor(m));
});

const selectionAtMax = computed(
  () =>
    maxSelectedLimit.value != null && props.modelValue.length >= maxSelectedLimit.value,
);

/** Текст на триггере: 0 — placeholder, 1 — один элемент, 2+ — первый элемент (бейдж +N отдельно). */
const triggerLabel = computed(() => {
  const len = props.modelValue.length;
  if (len === 0) return props.placeholder;
  const first = props.options.find((o) => o.value === props.modelValue[0]);
  return first?.label ?? String(props.modelValue[0]);
});

const selectedOptions = computed(() => {
  const set = new Set(props.modelValue);
  return props.options.filter((o) => set.has(o.value));
});

/** Порядок как в modelValue (для триггера: первый выбранный + счётчик остальных). */
const selectedOptionsInOrder = computed(() => {
  const map = new Map(props.options.map((o) => [o.value, o]));
  return props.modelValue
    .map((v) => map.get(v))
    .filter((o): o is MultiSelectOption => o != null);
});

const badgesMoreTooltipText = computed(() =>
  selectedOptionsInOrder.value
    .slice(1)
    .map((o) => o.label)
    .join('\n'),
);

const badgesTriggerMultiline = computed(
  () => props.displayMode === 'badges' && selectedOptions.value.length > 0,
);

function toggle(value: string | number) {
  const set = new Set(props.modelValue);
  if (set.has(value)) {
    set.delete(value);
  } else {
    const cap = maxSelectedLimit.value;
    if (cap != null && set.size >= cap) return;
    set.add(value);
  }
  emit('update:modelValue', Array.from(set));
}

function selectAllFiltered() {
  const cap = maxSelectedLimit.value;
  const current = new Set(props.modelValue);
  for (const opt of filteredOptions.value) {
    if (opt.disabled) continue;
    if (cap != null && current.size >= cap) break;
    current.add(opt.value);
  }
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
  <DropdownMenuRoot v-model:open="menuOpen">
    <DropdownMenuTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :class="cn(
          'flex min-w-[120px] justify-between gap-2 rounded-md border border-input bg-background px-3 text-sm font-normal shadow-sm transition-colors',
          badgesTriggerMultiline
            ? 'min-h-8 h-auto items-start py-1.5'
            : 'h-8 items-center py-1.5',
          'hover:bg-accent hover:text-accent-foreground',
          'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
          'disabled:pointer-events-none disabled:opacity-50',
          'data-[placeholder]:text-muted-foreground',
          triggerClass
        )"
      >
        <span
          :class="cn(
            'flex min-w-0 flex-1 gap-1.5',
            badgesTriggerMultiline ? 'flex-wrap content-center items-center' : 'items-center',
          )"
        >
          <template v-if="displayMode === 'badges'">
            <template v-if="selectedOptions.length === 0">
              <span class="truncate text-muted-foreground">{{ placeholder }}</span>
            </template>
            <template v-else>
              <template v-if="selectedOptionsInOrder[0]">
                <span
                  class="inline-flex max-w-full min-w-0 shrink items-center gap-1 rounded-md bg-secondary/90 px-1.5 py-0.5"
                >
                  <img
                    v-if="selectedOptionsInOrder[0].imageUrl"
                    :src="selectedOptionsInOrder[0].imageUrl"
                    alt=""
                    class="h-4 w-4 shrink-0 rounded object-cover"
                  >
                  <span
                    :class="cn(
                      'min-w-0 truncate text-xs font-medium',
                      selectedOptionsInOrder.length > 1 ? 'max-w-[10rem]' : 'max-w-[11rem]',
                      selectedOptionsInOrder[0].badgeClass,
                    )"
                  >
                    {{ selectedOptionsInOrder[0].label }}
                  </span>
                </span>
                <Tooltip
                  v-if="selectedOptionsInOrder.length > 1"
                  :content="badgesMoreTooltipText"
                  side="top"
                  class="max-w-sm whitespace-pre-line"
                >
                  <span
                    class="inline-flex shrink-0 cursor-default items-center rounded-md bg-secondary px-1.5 py-0.5 text-xs font-medium text-secondary-foreground"
                  >
                    +{{ selectedOptionsInOrder.length - 1 }}
                  </span>
                </Tooltip>
              </template>
            </template>
          </template>
          <template v-else>
            <span class="truncate" :class="{ 'text-muted-foreground': modelValue.length === 0 }">
              {{ triggerLabel }}
            </span>
            <span
              v-if="modelValue.length > 1"
              class="shrink-0 rounded bg-secondary px-1.5 py-0.5 text-xs font-medium text-secondary-foreground"
            >
              +{{ modelValue.length - 1 }}
            </span>
          </template>
        </span>
        <svg
          :class="cn('h-4 w-4 shrink-0 opacity-50', badgesTriggerMultiline && 'mt-0.5 self-center')"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
    </DropdownMenuTrigger>
    <ClientOnly>
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
            ref="searchInputRef"
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="flex h-8 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            @keydown.stop
          >
        </div>
        <div v-if="!hideActions" class="flex gap-1 border-b px-2 py-1.5 text-xs">
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
              (opt.disabled || (selectionAtMax && !selectedSet.has(opt.value))) &&
                'cursor-not-allowed opacity-50',
            )"
          >
            <input
              type="checkbox"
              :checked="selectedSet.has(opt.value)"
              :disabled="opt.disabled || (selectionAtMax && !selectedSet.has(opt.value))"
              class="h-4 w-4 shrink-0 rounded border-input"
              @change="
                !(opt.disabled || (selectionAtMax && !selectedSet.has(opt.value))) && toggle(opt.value)
              "
            >
            <img
              v-if="opt.imageUrl"
              :src="opt.imageUrl"
              alt=""
              class="h-5 w-5 shrink-0 rounded object-cover"
            >
            <template v-if="displayMode === 'badges'">
              <span :class="cn('min-w-0 truncate text-sm font-medium', opt.badgeClass)">{{ opt.label }}</span>
            </template>
            <span v-else class="min-w-0 truncate">{{ opt.label }}</span>
          </label>
        </div>
      </DropdownContent>
    </DropdownMenuPortal>
    </ClientOnly>
  </DropdownMenuRoot>
</template>
