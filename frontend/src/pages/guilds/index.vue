<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Badge, Button, Avatar, Input } from '@/shared/ui';
import { ref } from 'vue';

const search = ref('');
const guilds = [
  { name: 'Dragon Slayers', game: 'World of Warcraft', members: 24, level: 'Рейд', tag: 'DS' },
  { name: 'Crystal Guard', game: 'FF XIV', members: 30, level: 'Хардкор', tag: 'CG' },
  { name: 'Shadow Legion', game: 'Black Desert', members: 15, level: 'Казуал', tag: 'SL' },
];
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Гильдии</h1>
      <p class="mb-8 text-muted-foreground">
        Найдите гильдию под свой стиль игры или создайте свою.
      </p>

      <div class="mb-8">
        <Input
          v-model="search"
          placeholder="Поиск по названию или игре..."
          class="max-w-md"
        />
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <Card
          v-for="(g, i) in guilds"
          :key="g.name"
          class="transition-all hover:shadow-md animate-in fade-in slide-in-from-bottom-3"
          :style="{ animationDelay: `${i * 100}ms`, animationDuration: '400ms', animationFillMode: 'backwards' }"
        >
          <CardHeader class="flex flex-row items-center gap-4 space-y-0 pb-2">
            <Avatar :fallback="g.tag" class="h-12 w-12 rounded-lg" />
            <div class="flex-1 space-y-1">
              <CardTitle class="text-lg">{{ g.name }}</CardTitle>
              <p class="text-sm text-muted-foreground">{{ g.game }} · {{ g.members }} участников</p>
            </div>
            <Badge variant="outline">{{ g.level }}</Badge>
          </CardHeader>
          <CardContent>
            <Button variant="outline" size="sm" class="w-full">Подробнее</Button>
          </CardContent>
        </Card>
      </div>

      <div class="mt-8 text-center">
        <Button>Создать гильдию</Button>
      </div>
    </div>
  </div>
</template>
