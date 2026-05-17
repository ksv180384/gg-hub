<script setup lang="ts">
import { Button } from '@/shared/ui';
import { cn } from '@/shared/lib/utils';

interface Props {
  title: string;
  ariaLabel?: string;
  class?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'click', ev: MouseEvent): void;
}>();
</script>

<template>
  <Button
    variant="ghost"
    size="sm"
    :aria-label="props.ariaLabel ?? props.title"
    :title="props.title"
    :class="
      cn(
        'h-9 w-9 cursor-pointer bg-muted/70 p-0 text-foreground transition-colors hover:bg-muted/80',
        props.class
      )
    "
    @click="emit('click', $event)"
  >
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <path d="M15 18l-6-6 6-6" />
    </svg>
  </Button>
</template>

