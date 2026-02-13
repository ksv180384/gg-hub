<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: string;
  type?: string;
  modelValue?: string;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
});

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const model = computed({
  get: () => props.modelValue ?? '',
  set: (v: string) => emit('update:modelValue', v),
});
</script>

<template>
  <input
    v-model="model"
    :type="type"
    :class="cn(
      'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      props.class
    )"
    v-bind="$attrs"
  >
</template>
