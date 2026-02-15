<script setup lang="ts">
import { DropdownMenuSubTrigger as Root } from 'radix-vue';
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
      'flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none focus:bg-accent data-[state=open]:bg-accent',
      props.class
    )"
  >
    <slot />
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-auto h-4 w-4">
      <path d="m9 18 6-6-6-6"/>
    </svg>
  </Root>
</template>
