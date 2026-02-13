<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Avatar, Skeleton } from '@/shared/ui';
import { RouterLink } from 'vue-router';
</script>

<template>
  <div class="container py-8 md:py-12">
    <!-- Hero -->
    <section class="mx-auto flex max-w-4xl flex-col items-center gap-6 text-center">
      <Badge variant="secondary" class="animate-in fade-in slide-in-from-bottom-2 duration-500">
        Добро пожаловать в мир MMORPG
      </Badge>
      <h1 class="text-4xl font-bold tracking-tight sm:text-5xl md:text-6xl animate-in fade-in slide-in-from-bottom-3 duration-700">
        Найди свою гильдию.<br />
        <span class="text-primary">Играй вместе.</span>
      </h1>
      <p class="max-w-2xl text-lg text-muted-foreground animate-in fade-in slide-in-from-bottom-4 duration-1000">
        Социальная сеть для игроков MMORPG: новости, гильдии, рейды и единомышленники.
      </p>
      <div class="flex flex-wrap justify-center gap-4 animate-in fade-in slide-in-from-bottom-4 duration-1000 delay-150">
        <RouterLink to="/register">
          <Button size="lg" class="rounded-lg">
            Создать аккаунт
          </Button>
        </RouterLink>
        <RouterLink to="/news">
          <Button variant="outline" size="lg" class="rounded-lg">
            Читать новости
          </Button>
        </RouterLink>
      </div>
    </section>

    <!-- Карточки фич -->
    <section class="mx-auto mt-16 grid max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <Card
        v-for="(f, i) in [
          { title: 'Гильдии', desc: 'Создавайте гильдии, находите рейд-группы и единомышленников.', to: '/guilds', icon: '⚔' },
          { title: 'Новости', desc: 'Актуальные патчи, ивенты и гайды по любимым играм.', to: '/news', icon: '📜' },
          { title: 'Профили', desc: 'Покажите свой прогресс, достижения и любимые классы.', to: '/', icon: '🏆' },
        ]"
        :key="f.to"
        class="transition-all hover:shadow-md animate-in fade-in slide-in-from-bottom-4"
        :style="{ animationDelay: `${(i + 1) * 100 }ms`, animationDuration: '500ms', animationFillMode: 'backwards' }"
      >
        <CardHeader>
          <span class="mb-2 text-2xl">{{ f.icon }}</span>
          <CardTitle>{{ f.title }}</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-sm text-muted-foreground">{{ f.desc }}</p>
          <RouterLink :to="f.to">
            <Button variant="link" class="mt-2 px-0">Подробнее →</Button>
          </RouterLink>
        </CardContent>
      </Card>
    </section>

    <!-- Пример ленты (скелетоны) -->
    <section class="mx-auto mt-16 max-w-2xl">
      <h2 class="mb-6 text-2xl font-semibold">Лента активности</h2>
      <div class="space-y-4">
        <Card v-for="i in 3" :key="i" class="overflow-hidden">
          <CardContent class="flex gap-4 p-4">
            <Skeleton class="h-10 w-10 shrink-0 rounded-full" />
            <div class="flex-1 space-y-2">
              <Skeleton class="h-4 w-1/3" />
              <Skeleton class="h-3 w-full" />
              <Skeleton class="h-3 w-2/3" />
            </div>
          </CardContent>
        </Card>
      </div>
    </section>
  </div>
</template>
