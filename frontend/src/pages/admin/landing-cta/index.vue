<script setup lang="ts">
import { Button, Card, CardContent, CardHeader, CardTitle } from '@/shared/ui';
import RelativeTime from '@/shared/ui/relative-time/RelativeTime.vue';
import { getLandingCtaClickStats, type LandingCtaClickStats } from '@/shared/api/landingApi';
import { ref, onMounted } from 'vue';

const stats = ref<LandingCtaClickStats | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    stats.value = await getLandingCtaClickStats();
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить данные';
    stats.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Клики на лендинге</h1>
      <p class="mb-8 max-w-2xl text-muted-foreground">
        Сколько раз посетители нажимали «Начать бесплатно» и «Создать аккаунт» на главной странице (модалка «сайт в
        разработке»).
      </p>

      <div class="mb-6 flex flex-wrap gap-3">
        <Button type="button" variant="outline" :disabled="loading" @click="load">Обновить</Button>
      </div>

      <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ error }}
      </div>

      <div v-if="loading" class="text-muted-foreground">Загрузка...</div>

      <div v-else-if="stats" class="grid gap-4 sm:grid-cols-3">
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-base font-medium text-muted-foreground">Всего кликов</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-3xl font-bold tabular-nums">{{ stats.total }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-base font-medium text-muted-foreground">«Начать бесплатно»</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-3xl font-bold tabular-nums">{{ stats.start_free }}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-base font-medium text-muted-foreground">«Создать аккаунт»</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-3xl font-bold tabular-nums">{{ stats.create_account }}</p>
          </CardContent>
        </Card>
      </div>

      <p v-if="stats?.last_click_at" class="mt-6 text-sm text-muted-foreground">
        Последний клик:
        <RelativeTime :date="stats.last_click_at" tag="time" class="font-medium text-foreground" />
      </p>
    </div>
  </div>
</template>
