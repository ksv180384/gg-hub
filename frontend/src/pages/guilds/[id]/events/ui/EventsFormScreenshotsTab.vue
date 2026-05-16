<script setup lang="ts">
import { Card, CardHeader, CardTitle, CardContent, Button, Input } from '@/shared/ui';

type ScreenshotRow = { url: string; title: string };

const screenshots = defineModel<ScreenshotRow[]>('screenshots', { required: true });

const emit = defineEmits<{
  (e: 'addScreenshotRow'): void;
  (e: 'removeScreenshotRow', index: number): void;
}>();
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">Скриншоты</CardTitle>
    </CardHeader>
    <CardContent class="space-y-3">
      <div
        v-for="(shot, index) in screenshots"
        :key="index"
        class="flex flex-col gap-2 rounded-md border p-2 md:flex-row md:items-center"
      >
        <div class="min-w-0 flex-1 space-y-1">
          <Input v-model="shot.url" type="url" placeholder="Ссылка на скриншот *" />
          <Input
            v-model="shot.title"
            type="text"
            placeholder="Название скриншота (необязательно)"
          />
        </div>
        <div class="flex justify-end md:items-start md:justify-center md:pl-2">
          <Button
            type="button"
            variant="ghost"
            size="icon"
            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
            @click="emit('removeScreenshotRow', index)"
          >
            ✕
          </Button>
        </div>
      </div>
      <Button type="button" variant="outline" size="sm" @click="emit('addScreenshotRow')">
        Добавить скриншот
      </Button>
    </CardContent>
  </Card>
</template>
