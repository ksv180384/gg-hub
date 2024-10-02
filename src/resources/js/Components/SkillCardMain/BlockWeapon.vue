<script setup>
const props = defineProps({
  weapon: { type: Object, default: {} },
});
</script>

<template>
  <div>
    {{ weapon.name }}
  </div>
</template>

<style scoped>

</style>
