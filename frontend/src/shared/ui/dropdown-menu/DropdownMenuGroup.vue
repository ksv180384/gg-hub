<script setup lang="ts">
import { DropdownMenuGroup as Root } from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: string;
}

const props = defineProps<Props>();
</script>

<template>
  <Root :class="cn(props.class)" role="group">
    <slot />
  </Root>
</template>
