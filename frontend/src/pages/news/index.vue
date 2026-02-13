<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Badge, Button, Separator } from '@/shared/ui';
import { RouterLink } from 'vue-router';

const news = [
  { id: 1, title: 'Обновление 2.5: новый рейд и класс', date: '8 фев 2025', tag: 'Патч', game: 'World of Warcraft' },
  { id: 2, title: 'Ивент «Зимняя битва» — награды и расписание', date: '5 фев 2025', tag: 'Ивент', game: 'Final Fantasy XIV' },
  { id: 3, title: 'Баланс классов в PvP: что изменилось', date: '1 фев 2025', tag: 'Гайд', game: 'Общее' },
];
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-3xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Новости</h1>
      <p class="text-muted-foreground">
        Патчи, ивенты и гайды по MMORPG.
      </p>
      <Separator class="my-8" />

      <ul class="space-y-6">
        <li
          v-for="(n, i) in news"
          :key="n.id"
          class="animate-in fade-in slide-in-from-bottom-3"
          :style="{ animationDelay: `${i * 80}ms`, animationDuration: '400ms', animationFillMode: 'backwards' }"
        >
          <Card class="overflow-hidden transition-all hover:shadow-md">
            <RouterLink :to="`/news/${n.id}`" class="block">
              <CardHeader class="pb-2">
                <div class="flex flex-wrap items-center gap-2">
                  <Badge variant="secondary">{{ n.tag }}</Badge>
                  <span class="text-xs text-muted-foreground">{{ n.game }}</span>
                </div>
                <CardTitle class="mt-2 text-lg">{{ n.title }}</CardTitle>
                <p class="text-sm text-muted-foreground">{{ n.date }}</p>
              </CardHeader>
              <CardContent class="pt-0">
                <Button variant="ghost" size="sm" class="px-0">Читать далее →</Button>
              </CardContent>
            </RouterLink>
          </Card>
        </li>
      </ul>
    </div>
  </div>
</template>
