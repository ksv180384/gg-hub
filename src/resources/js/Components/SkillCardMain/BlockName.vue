<script setup>
const props = defineProps({
  name: { type: String, default: '' },
});
</script>

<template>
  <div>
    {{ name }}
  </div>
</template>

<style scoped>

</style>
