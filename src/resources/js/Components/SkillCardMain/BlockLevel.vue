<script setup>
const props = defineProps({
  level: { type: Object, default: null },
});
</script>

<template>
<div>
  {{ level.content_ru }}
</div>
</template>

<style scoped>

</style>
