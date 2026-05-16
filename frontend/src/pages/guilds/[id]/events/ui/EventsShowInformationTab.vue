<script setup lang="ts">
import { Card, CardHeader, CardTitle, CardContent } from '@/shared/ui';
import type { EventHistoryItem } from '@/shared/api/eventHistoryApi';

defineProps<{
  item: EventHistoryItem;
  formatDateTime: (iso: string | null) => string;
}>();
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">Основная информация</CardTitle>
    </CardHeader>
    <CardContent class="space-y-4">
      <div v-if="item.occurred_at" class="space-y-1">
        <p class="text-xs font-medium text-muted-foreground">Дата и время</p>
        <p class="text-sm">{{ formatDateTime(item.occurred_at) }}</p>
      </div>
      <div v-if="item.dkp?.base_points != null" class="space-y-1">
        <p class="text-xs font-medium text-muted-foreground">Очки ДКП за посещение</p>
        <p class="text-sm">{{ item.dkp.base_points }}</p>
      </div>
      <div v-if="item.description" class="space-y-1">
        <p class="text-xs font-medium text-muted-foreground">Описание</p>
        <p class="text-sm whitespace-pre-wrap">{{ item.description }}</p>
      </div>
      <p
        v-if="!item.occurred_at && !item.description && item.dkp?.base_points == null"
        class="text-sm text-muted-foreground"
      >
        Дополнительная информация не указана.
      </p>
    </CardContent>
  </Card>
</template>
