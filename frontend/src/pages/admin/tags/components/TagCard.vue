<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { Card, CardHeader, CardTitle, Button, Badge } from '@/shared/ui';
import type { Tag } from '@/shared/api/tagsApi';

defineProps<{
  tag: Tag;
  toggling: boolean;
  canEdit?: boolean;
  canHide?: boolean;
  canDelete?: boolean;
}>();

const emit = defineEmits<{
  (e: 'toggle-hidden'): void;
  (e: 'delete'): void;
}>();
</script>

<template>
  <Card>
    <CardHeader class="flex flex-row items-center justify-between">
      <div>
        <CardTitle class="text-base">{{ tag.name }}</CardTitle>
        <p class="mt-1 text-sm text-muted-foreground">Слаг: {{ tag.slug }}</p>
        <p v-if="tag.created_by" class="mt-0.5 text-xs text-muted-foreground">
          Добавил: {{ tag.created_by.name }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Badge v-if="tag.is_hidden" variant="secondary">Скрыт</Badge>
        <Button
          v-if="canHide"
          variant="ghost"
          size="icon"
          class="h-8 w-8"
          :disabled="toggling"
          :aria-label="tag.is_hidden ? 'Показать' : 'Скрыть'"
          :title="tag.is_hidden ? 'Показать' : 'Скрыть'"
          @click="emit('toggle-hidden')"
        >
          <template v-if="toggling">
            <span class="text-sm">…</span>
          </template>
          <template v-else-if="tag.is_hidden">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49" />
              <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242" />
              <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-4.363" />
              <path d="m2 2 20 20" />
            </svg>
          </template>
          <template v-else>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </template>
        </Button>
        <RouterLink v-if="canEdit" :to="{ name: 'admin-tags-edit', params: { id: tag.id } }">
          <Button variant="ghost" size="icon" class="h-8 w-8" aria-label="Редактировать" title="Редактировать">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
              <path d="m15 5 4 4" />
            </svg>
          </Button>
        </RouterLink>
        <Button
          v-if="canDelete"
          variant="ghost"
          size="icon"
          class="h-9 w-9 shrink-0 min-h-9 min-w-9 touch-manipulation text-destructive hover:text-destructive hover:bg-destructive/10"
          aria-label="Удалить"
          title="Удалить"
          @click="emit('delete')"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18" />
            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
            <line x1="10" x2="10" y1="11" y2="17" />
            <line x1="14" x2="14" y1="11" y2="17" />
          </svg>
        </Button>
      </div>
    </CardHeader>
  </Card>
</template>
