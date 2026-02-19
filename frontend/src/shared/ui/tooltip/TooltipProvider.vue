<script setup lang="ts">
import { TooltipProvider as Root } from 'radix-vue';

interface Props {
  delayDuration?: number;
  skipDelayDuration?: number;
}

withDefaults(defineProps<Props>(), {
  delayDuration: 200,
  skipDelayDuration: 100,
});
</script>

<template>
  <Root :delay-duration="delayDuration" :skip-delay-duration="skipDelayDuration">
    <slot />
  </Root>
</template>
