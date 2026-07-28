<script setup lang="ts">
import { cn } from '@/shared/lib/utils';
</script>

<template>
  <thead :class="cn('[&_tr]:border-b', $attrs.class as string)">
    <slot />
  </thead>
</template>
