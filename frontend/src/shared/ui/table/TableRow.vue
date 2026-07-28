<script setup lang="ts">
import { cn } from '@/shared/lib/utils';
</script>

<template>
  <tr
    :class="
      cn(
        'border-b transition-colors hover:bg-muted/60 data-[state=selected]:bg-muted',
        $attrs.class as string,
      )
    "
  >
    <slot />
  </tr>
</template>
