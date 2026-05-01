<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import type { GuildEvent } from '@/shared/api/eventsApi';
import type { CalendarEvent } from '@/shared/ui';

defineProps<{
  selectedDateLabel: string;
  events: GuildEvent[];
  canAddEvent: boolean;
  canEditEvent: boolean;
  canDeleteEvent: boolean;
}>();

const emit = defineEmits<{
  create: [];
  open: [event: CalendarEvent];
  edit: [event: CalendarEvent];
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
          <div class="min-w-0 flex-1">
            <button
              type="button"
              class="block w-full truncate text-left font-medium hover:underline"
              @click="emit('open', { id: ev.id, title: ev.title, starts_at: ev.starts_at, ends_at: ev.ends_at })"
            >
              {{ ev.title }}
            </button>
            <p
              v-if="ev.declined_characters?.length"
              class="mt-0.5 truncate text-xs text-muted-foreground"
              :title="`Не смогут: ${ev.declined_characters.map((c) => c.name).join(', ')}`"
            >
              Не смогут: {{ ev.declined_characters.map((c) => c.name).join(', ') }}
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-0">
            <Button
              v-if="canEditEvent"
              variant="ghost"
              size="icon"
              class="h-8 w-8 text-muted-foreground hover:text-foreground"
              aria-label="Редактировать"
              type="button"
              @click="
                emit('edit', {
                  id: ev.id,
                  title: ev.title,
                  starts_at: ev.starts_at,
                  ends_at: ev.ends_at,
                })
              "
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z" />
              </svg>
            </Button>
            <Button
              v-if="canDeleteEvent"
              variant="ghost"
              size="icon"
              class="h-8 w-8 text-muted-foreground hover:text-destructive"
              aria-label="Удалить"
              type="button"
              @click="emit('delete', ev)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            </Button>
          </div>
        </li>
      </ul>
      <p v-else class="text-sm text-muted-foreground">Нет событий на эту дату.</p>
    </CardContent>
  </Card>
</template>
