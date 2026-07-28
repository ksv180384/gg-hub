<script setup lang="ts">
import { cn } from '@/shared/lib/utils';
</script>

<template>
  <td :class="cn('p-4 align-middle', $attrs.class as string)">
    <slot />
  </td>
</template>
