<script setup lang="ts">
import { RouterView } from 'vue-router';
import { TooltipProvider } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import ToastViewport from '@/shared/ui/toast/ToastViewport.vue';
</script>

<template>
  <TooltipProvider>
    <RouterView />
    <ClientOnly>
      <ToastViewport />
    </ClientOnly>
  </TooltipProvider>
</template>
