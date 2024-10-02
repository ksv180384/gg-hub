<script setup>
const props = defineProps({
  type: { type: Object, default: null },
});
</script>

<template>
<div>
  {{ type.content_ru }}
</div>
</template>

<style scoped>

</style>
