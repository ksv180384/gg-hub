<script setup lang="ts">
import { toastState } from '@/shared/lib/toast';
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="-translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-1 opacity-0"
    >
      <div
        v-if="toastState.message"
        class="fixed top-6 right-6 z-[200] max-w-[min(28rem,calc(100vw-2rem))] rounded-md border border-border bg-popover px-4 py-3 text-sm text-popover-foreground shadow-md"
        role="alert"
      >
        {{ toastState.message }}
      </div>
    </Transition>
  </Teleport>
</template>
