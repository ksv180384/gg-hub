<script setup lang="ts">
import { RouterView } from 'vue-router';
import { Spinner } from '@/shared/ui';
import { useRouteLoadingStore } from '@/stores/routeLoading';

const routeLoading = useRouteLoadingStore();
</script>

<template>
  <div class="relative">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="routeLoading.isLoading"
        class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-4 bg-background/95 backdrop-blur-sm"
        aria-live="polite"
        aria-busy="true"
      >
        <Spinner />
        <p class="text-sm text-muted-foreground">Загрузка…</p>
      </div>
    </Transition>
    <RouterView />
  </div>
</template>
