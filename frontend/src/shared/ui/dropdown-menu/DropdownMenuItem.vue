<script setup lang="ts">
import { DropdownMenuItem as Root } from 'radix-vue';
import type { ClassValue } from 'clsx';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: ClassValue;
  disabled?: boolean;
}

const props = defineProps<Props>();
</script>

<template>
  <Root
    :disabled="props.disabled"
    :class="cn(
      'relative flex cursor-default select-none items-center rounded-md px-2.5 py-2 text-sm outline-none transition-colors focus:bg-accent/70 focus:text-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class
    )"
  >
    <slot />
  </Root>
</template>
