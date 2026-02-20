<script setup lang="ts">
import { SelectRoot as Root } from 'radix-vue';

interface Props {
  modelValue?: string;
  disabled?: boolean;
  required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  required: false,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
  <Root
    :model-value="props.modelValue"
    :disabled="props.disabled"
    :required="props.required"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <slot />
  </Root>
</template>
