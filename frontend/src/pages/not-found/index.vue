<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { Button } from '@/shared/ui';
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
        <RouterLink to="/">
          <Button>На главную</Button>
        </RouterLink>
        <RouterLink to="/guilds">
          <Button variant="outline">Гильдии</Button>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
