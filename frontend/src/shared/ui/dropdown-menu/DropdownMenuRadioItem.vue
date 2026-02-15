<script setup lang="ts">
import {
  DropdownMenuRadioItem as Root,
  DropdownMenuItemIndicator,
} from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  value: string;
  class?: string;
}

const props = defineProps<Props>();
</script>

<template>
  <Root
    :value="props.value"
    :class="cn(
      'relative flex cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class
    )"
  >
    <span class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
      <DropdownMenuItemIndicator class="h-4 w-4">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6 9 17l-5-5"/>
        </svg>
      </DropdownMenuItemIndicator>
    </span>
    <slot />
  </Root>
</template>
