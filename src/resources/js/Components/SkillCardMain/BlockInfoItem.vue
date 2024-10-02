<script setup>
const props = defineProps({
  info: { type: Object, default: {} },
});
</script>

<template>
  <div class="info-item">
    <div class="info-item-label">
      {{ info.name }}
    </div>
    <div class="info-item-content">
      {{ info.value }}
    </div>
  </div>
</template>

<style scoped>
.info-item{
  @apply flex justify-between;
}

.info-item-label{

}

.info-item-content{

}
</style>
