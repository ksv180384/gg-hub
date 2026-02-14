<script setup lang="ts">
import { DropdownMenuTrigger as Root } from 'radix-vue';

interface Props {
  asChild?: boolean;
  disabled?: boolean;
  class?: string;
}

const props = withDefaults(defineProps<Props>(), {
  asChild: false,
});
</script>

<template>
  <Root :as-child="props.asChild" :disabled="props.disabled" :class="props.class">
    <slot />
  </Root>
</template>
