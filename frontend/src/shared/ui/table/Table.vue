<script setup lang="ts">
import { useAttrs } from 'vue';
import { cn } from '@/shared/lib/utils';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
</script>

<template>
  <div class="relative w-full">
    <table
      v-bind="attrs"
      :class="cn('w-full caption-bottom text-sm', attrs.class as string | undefined)"
    >
      <slot />
    </table>
  </div>
</template>
