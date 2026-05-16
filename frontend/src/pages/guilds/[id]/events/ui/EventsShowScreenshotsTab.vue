<script setup lang="ts">
import { Card, CardHeader, CardTitle, CardContent, LightboxImage } from '@/shared/ui';
import type { EventHistoryItem } from '@/shared/api/eventHistoryApi';

defineProps<{
  item: EventHistoryItem | null;
  loading: boolean;
}>();
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">Скриншоты</CardTitle>
    </CardHeader>
    <CardContent>
      <div v-if="loading" class="flex flex-wrap gap-3">
        <div
          v-for="n in 4"
          :key="n"
          class="h-24 w-24 rounded-lg border bg-muted/40"
        />
      </div>

      <div
        v-else-if="item && (item.screenshots?.length ?? 0) > 0"
        class="flex flex-wrap gap-3"
      >
        <LightboxImage
          v-for="s in item.screenshots"
          :key="s.id"
          :src="s.url"
          :alt="s.title || 'Скриншот'"
          :title="s.title || 'Скриншот'"
          button-class="rounded-lg border p-1 transition-colors hover:bg-accent"
          img-class="h-24 w-24 rounded object-cover sm:h-28 sm:w-28"
        />
      </div>

      <p v-else class="text-sm text-muted-foreground">
        Нет скриншотов.
      </p>
    </CardContent>
  </Card>
</template>
