<script setup>
import { Head } from '@inertiajs/vue3';
import { startParserInfoAllSkills } from '@/services/parserService.js';

import AdmLayout from '@/Layouts/AdmLayout.vue';

const startParser = async () => {
  await startParserInfoAllSkills();
}
</script>

<template>
  <AdmLayout>
    <h1>okokok !!!</h1>
    <h1>okokok !!!</h1>
    <h1>okokok !!!</h1>
    <h1>okokok !!!</h1>
    <h1>okokok !!!</h1>
    <h1>okokok !!!</h1>
    <Button
      severity="info"
      text
      raised
      size="small"
      @click="startParser"
    >
      Запустить парсер сбора инфомации о всех умениях
    </Button>
  </AdmLayout>
</template>

<style scoped>

</style>
