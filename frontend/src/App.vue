<script setup lang="ts">
import { RouterView } from 'vue-router';
import { TooltipProvider } from '@/shared/ui';
</script>

<template>
  <TooltipProvider>
    <RouterView />
  </TooltipProvider>
</template>
