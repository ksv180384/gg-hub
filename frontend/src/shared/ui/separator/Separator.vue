<script setup lang="ts">
import { Separator as SeparatorPrimitive } from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  orientation?: 'horizontal' | 'vertical';
  class?: string;
}

const props = withDefaults(defineProps<Props>(), {
  orientation: 'horizontal',
});
</script>

<template>
  <SeparatorPrimitive
    :orientation="props.orientation"
    :class="cn(
      'shrink-0 bg-border',
      props.orientation === 'horizontal' ? 'h-[1px] w-full' : 'h-full w-[1px]',
      props.class
    )"
  />
</template>
