<script setup>
const props = defineProps({
  textOriginal: { type: String },
  textRu: { type: String },
});
</script>

<template>
  <span class="item">
    <span class="item-original">{{ textOriginal }}</span>
    <span class="item-ru">{{ textRu }}</span>
  </span>
</template>

<style scoped>
.item{
  @apply flex flex-col bg-amber-500;
}

.item-original{

}

.item-ru{

}
</style>
