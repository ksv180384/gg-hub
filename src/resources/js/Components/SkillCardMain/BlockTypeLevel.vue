<script setup>
const props = defineProps({
  typeLevel: { type: Object, default: null },
});
</script>

<template>
  <div>
    {{ typeLevel.content_ru }}
  </div>
</template>

<style scoped>

</style>
