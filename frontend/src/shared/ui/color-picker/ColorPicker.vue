<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'radix-vue';
import { Vue3ColorPicker } from '@cyhnkckali/vue3-color-picker';
import { Button } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { cn } from '@/shared/lib/utils';

interface PresetColor {
  label: string;
  value: string;
}

interface Props {
  modelValue?: string;
  id?: string;
  class?: string;
  disabled?: boolean;
  /**
   * Предустановленные цвета (для палитры внутри vue3-color-picker).
   * Значения лучше передавать как HEX8 (#RRGGBBAA), но допустим и #RRGGBB — библиотека нормализует.
   */
  presets?: PresetColor[];
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  disabled: false,
  presets: () => [
    { label: 'Золотой', value: '#D4AF37FF' },
    { label: 'Фиолетовый', value: '#8B5CF6FF' },
    { label: 'Синий', value: '#3B82F6FF' },
    { label: 'Белый', value: '#FFFFFFFF' },
  ],
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);

const model = computed({
  get: () => props.modelValue ?? '',
  set: (v: string) => emit('update:modelValue', v),
});

const displayValue = computed(() => (model.value || '').trim() || '—');

const localStorageKey = 'ck-cp-local-color-list';
if (typeof window !== 'undefined') {
  try {
    const existing = window.localStorage.getItem(localStorageKey);
    if (!existing) {
      window.localStorage.setItem(
        localStorageKey,
        JSON.stringify(props.presets.map((p) => p.value))
      );
    }
  } catch {
    // ignore (private mode / restricted storage)
  }
}
</script>

<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        :id="id"
        type="button"
        variant="outline"
        size="default"
        :disabled="disabled"
        :class="cn('h-9 w-full justify-start gap-2 px-3 font-normal', props.class)"
      >
        <span
          class="h-4 w-4 rounded-sm border"
          :style="{ background: model || '#00000000' }"
          aria-hidden="true"
        />
        <span class="font-mono text-sm">{{ displayValue }}</span>
      </Button>
    </PopoverTrigger>

    <ClientOnly>
    <PopoverPortal>
      <PopoverContent
        side="bottom"
        align="start"
        :side-offset="6"
        :class="cn(
          // Не добавляем свою подложку/рамку: у vue3-color-picker есть собственный контейнер.
          'z-50 w-[min(22rem,calc(100vw-2rem))] bg-transparent p-0 text-popover-foreground shadow-none border-0',
          'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
          'data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2'
        )"
      >
        <!-- Пикер с квадратом + hue/alpha + eyedropper + HEX/форматы, как на shadcn-form -->
        <Vue3ColorPicker
          v-model="model"
          mode="solid"
          theme="dark"
          type="HEX8"
          inputType="RGB"
          :disabled="disabled"
          :showColorList="true"
          :showAlpha="true"
          :showEyeDrop="true"
          :showPickerMode="false"
          :showButtons="false"
        />
      </PopoverContent>
    </PopoverPortal>
    </ClientOnly>
  </PopoverRoot>
</template>

<style scoped>
/* Встроенная палитра vue3-color-picker: скрываем кнопку "+" (сохранить в палитру). */
:deep(.cp-btn-save-color) {
  display: none !important;
}
</style>
