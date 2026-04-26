<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import type { GuildEvent } from '@/shared/api/eventsApi';
import type { CalendarEvent } from '@/shared/ui';

defineProps<{
  selectedDateLabel: string;
  events: GuildEvent[];
  canAddEvent: boolean;
  canDeleteEvent: boolean;
}>();

const emit = defineEmits<{
  create: [];
  open: [event: CalendarEvent];
  delete: [event: GuildEvent];
}>();
</script>

<template>
  <Card class="w-full lg:w-80 shrink-0">
    <CardHeader class="pb-2">
      <CardTitle class="text-base">События на {{ selectedDateLabel }}</CardTitle>
      <Button
        v-if="canAddEvent"
        variant="outline"
        size="sm"
        class="mt-2 w-full"
        type="button"
        @click="emit('create')"
      >
        Добавить событие
      </Button>
    </CardHeader>
    <CardContent class="pt-0">
      <ul v-if="events.length" class="space-y-2">
        <li
          v-for="ev in events"
          :key="ev.id"
          class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
        >
          <button
            type="button"
            class="min-w-0 flex-1 truncate text-left font-medium hover:underline"
            @click="emit('open', { id: ev.id, title: ev.title, starts_at: ev.starts_at, ends_at: ev.ends_at })"
          >
            {{ ev.title }}
          </button>
          <Button
            v-if="canDeleteEvent"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
            aria-label="Удалить"
            type="button"
            @click="emit('delete', ev)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
          </Button>
        </li>
      </ul>
      <p v-else class="text-sm text-muted-foreground">Нет событий на эту дату.</p>
    </CardContent>
  </Card>
</template>
