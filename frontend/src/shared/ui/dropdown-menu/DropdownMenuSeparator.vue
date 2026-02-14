<script setup lang="ts">
import { DropdownMenuSeparator as Root } from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: string;
}

const props = defineProps<Props>();
</script>

<template>
  <Root :class="cn('-mx-1 my-1 h-px bg-muted', props.class)" />
</template>
