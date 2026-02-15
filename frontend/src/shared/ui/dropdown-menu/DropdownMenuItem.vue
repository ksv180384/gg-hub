<script setup lang="ts">
import { DropdownMenuItem as Root } from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: string;
  disabled?: boolean;
}

const props = defineProps<Props>();
</script>

<template>
  <Root
    :disabled="props.disabled"
    :class="cn(
      'relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class
    )"
  >
    <slot />
  </Root>
</template>
