<script setup lang="ts">
import { RouterView } from 'vue-router';
import { Header } from '@/widgets/header';
</script>

<template>
  <div class="min-h-svh flex flex-col bg-background">
    <Header />
    <main class="flex-1">
      <RouterView />
    </main>
    <footer class="border-t py-6 md:py-0">
      <div class="container flex flex-col items-center justify-between gap-4 md:h-14 md:flex-row">
        <p class="text-center text-sm text-muted-foreground md:text-left">
          GG Hub — соцсеть для игроков MMORPG
        </p>
      </div>
    </footer>
  </div>
</template>
