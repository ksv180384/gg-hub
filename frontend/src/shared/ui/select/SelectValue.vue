<script setup lang="ts">
import { SelectValue as Root } from 'radix-vue';

interface Props {
  placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Выберите...',
});
</script>

<template>
  <Root :placeholder="props.placeholder">
    <slot />
  </Root>
</template>
