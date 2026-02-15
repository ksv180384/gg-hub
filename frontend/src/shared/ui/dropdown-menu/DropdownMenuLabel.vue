<script setup lang="ts">
import { DropdownMenuLabel as Root } from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: string;
}

const props = defineProps<Props>();
</script>

<template>
  <Root :class="cn('px-2 py-1.5 text-sm font-semibold', props.class)">
    <slot />
  </Root>
</template>
