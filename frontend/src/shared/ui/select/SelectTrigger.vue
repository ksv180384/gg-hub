<script setup lang="ts">
import { SelectTrigger as Root } from 'radix-vue';
import type { ClassValue } from 'clsx';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: ClassValue;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
});
</script>

<template>
  <Root
    :disabled="props.disabled"
    :class="cn(
      'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-border bg-muted/25 px-3 py-2 text-sm text-foreground shadow-none transition-colors duration-150 hover:border-primary/25 hover:bg-background focus:outline-none focus:border-primary/45 focus:bg-background focus:ring-2 focus:ring-primary/15 disabled:pointer-events-none disabled:bg-muted/40 disabled:opacity-70 data-[placeholder]:text-muted-foreground [&>span]:line-clamp-1',
      props.class
    )"
  >
    <slot />
    <span class="ml-2 shrink-0 text-muted-foreground" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m6 9 6 6 6-6" />
      </svg>
    </span>
  </Root>
</template>
