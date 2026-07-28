<script setup lang="ts">
import { cn } from '@/shared/lib/utils';
</script>

<template>
  <th
    :class="
      cn(
        'h-11 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-muted-foreground',
        $attrs.class as string,
      )
    "
  >
    <slot />
  </th>
</template>
