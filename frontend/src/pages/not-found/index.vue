<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <div class="container py-16 md:py-24">
    <div class="mx-auto max-w-lg text-center">
      <p class="text-sm font-medium text-muted-foreground">404</p>
      <h1 class="mt-2 text-3xl font-bold tracking-tight md:text-4xl">Страница не найдена</h1>
      <p class="mt-4 text-muted-foreground">
        Такого адреса нет или страница была удалена. Проверьте ссылку или перейдите на главную.
      </p>
      <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <RouterLink
          to="/"
          class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          На главную
        </RouterLink>
        <RouterLink
          to="/guilds"
          class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          Гильдии
        </RouterLink>
      </div>
    </div>
  </div>
</template>
