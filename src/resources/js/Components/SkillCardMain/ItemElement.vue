<script setup>
import { ref } from 'vue';

const props = defineProps({
  textOriginal: { type: String },
  textRu: { type: String },
});

const isShowOriginal = ref(true);
const toggleShowOriginal = () => {
  isShowOriginal.value = !isShowOriginal.value;
}
</script>

<template>
  <span class="item" @click="toggleShowOriginal">
    <span v-if="isShowOriginal" class="item-original" v-html="textOriginal.replace(/\n/g, '<br>')"/>
    <span v-else class="item-ru" v-html="textRu.replace(/\n/g, '<br>')"/>
    <button class="item-toggle">c</button>
  </span>
</template>

<style scoped>
.item{
  @apply inline relative bg-amber-500 rounded-r-xl box-decoration-clone ms-2 rounded;
}

.item-original{
  @apply box-decoration-clone ps-1;
}

.item-ru{
  @apply box-decoration-clone ps-1;
}

.item-toggle{
  @apply inline-flex justify-center items-center bg-amber-200 w-[24px] h-[24px] rounded-full cursor-pointer ms-2;
}
</style>
