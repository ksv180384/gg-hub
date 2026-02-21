<script setup lang="ts">
import GameSidebarContent from './GameSidebarContent.vue';
</script>

<template>
  <aside
    class="hidden md:flex w-56 shrink-0 flex-col border-r bg-[var(--sidebar)] text-[var(--sidebar-foreground)]"
    aria-label="Боковое меню"
  >
    <GameSidebarContent />
  </aside>
</template>
