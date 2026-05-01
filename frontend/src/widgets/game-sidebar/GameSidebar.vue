<script setup lang="ts">
import GameSidebarContent from './GameSidebarContent.vue';
</script>

<template>
  <aside
    class="hidden h-full min-h-0 w-full shrink-0 flex-col border-r bg-[var(--sidebar)] text-[var(--sidebar-foreground)] md:flex"
    aria-label="Боковое меню"
  >
    <GameSidebarContent />
  </aside>
</template>
