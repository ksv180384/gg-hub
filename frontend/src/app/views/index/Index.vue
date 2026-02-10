<script setup lang="ts">

import MainLayout from '@/app/layouts/MainLayout.vue';
import SpinWheel from '@/features/spin-wheel/ui/SpinWheel.vue';
import {onMounted, ref} from 'vue';

const options = ref<string[]>([]);

onMounted(() => {
  for (let i = 0; i < 40; i++){
    options.value.push(`Приз ${i}`)
  }
});
</script>

<template>

  <main-layout>
    <div>
      App--*-*-*
    </div>
    <div>
      <SpinWheel :options='options' :duration="10000" :size="600"/>
    </div>
  </main-layout>
</template>

<style scoped>

</style>
