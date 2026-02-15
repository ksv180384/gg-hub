<script setup lang="ts">
import { AvatarRoot, AvatarImage, AvatarFallback } from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: string;
  src?: string;
  alt?: string;
  fallback?: string;
}

const props = withDefaults(defineProps<Props>(), {
  fallback: '??',
});
</script>

<template>
  <AvatarRoot
    :class="cn(
      'relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full',
      props.class
    )"
  >
    <AvatarImage
      v-if="props.src"
      :src="props.src"
      :alt="props.alt ?? ''"
      class="aspect-square h-full w-full"
    />
    <AvatarFallback
      class="flex h-full w-full items-center justify-center rounded-full bg-muted text-sm font-medium"
    >
      {{ props.fallback }}
    </AvatarFallback>
  </AvatarRoot>
</template>
