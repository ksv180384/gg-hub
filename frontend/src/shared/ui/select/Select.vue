<script setup lang="ts">
/**
 * Переиспользуемый Select в стиле shadcn UI.
 * Обёртка над SelectRoot/Trigger/Value/Content/Item для простого выбора из списка опций.
 */
import SelectRoot from './SelectRoot.vue';
import SelectTrigger from './SelectTrigger.vue';
import SelectValue from './SelectValue.vue';
import SelectContent from './SelectContent.vue';
import SelectItem from './SelectItem.vue';
import type { SelectOption } from './types';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    options: SelectOption[];
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    triggerClass?: string;
    contentClass?: string;
  }>(),
  {
    placeholder: 'Выберите...',
    disabled: false,
    required: false,
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
  <SelectRoot
    :model-value="modelValue"
    :disabled="disabled"
    :required="required"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <SelectTrigger :class="triggerClass">
      <SelectValue :placeholder="placeholder" />
    </SelectTrigger>
    <SelectContent :class="contentClass">
      <SelectItem
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
        :disabled="opt.disabled"
      >
        {{ opt.label }}
      </SelectItem>
    </SelectContent>
  </SelectRoot>
</template>
