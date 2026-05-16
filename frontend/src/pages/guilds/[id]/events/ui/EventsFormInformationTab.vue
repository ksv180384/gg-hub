<script setup lang="ts">
import { Card, CardHeader, CardTitle, CardContent, Input, Label, Button } from '@/shared/ui';
import type { EventHistoryTitleDto } from '@/shared/api/eventHistoryTitlesApi';

defineProps<{
  title: string;
  occurredAt: string;
  description: string;
  dkpBasePoints: string;
  dkpEnabled: boolean;
  showEventTypesButton: boolean;
  titleSuggestions: EventHistoryTitleDto[];
  showTitleSuggestions: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:title', value: string): void;
  (e: 'update:occurredAt', value: string): void;
  (e: 'update:description', value: string): void;
  (e: 'update:dkpBasePoints', value: string): void;
  (e: 'searchTitleSuggestions', query: string): void;
  (e: 'hideTitleSuggestions'): void;
  (e: 'applyTitleSuggestion', suggestion: EventHistoryTitleDto): void;
  (e: 'editTitleSuggestion', suggestion: EventHistoryTitleDto): void;
  (e: 'deleteTitleSuggestion', suggestion: EventHistoryTitleDto): void;
  (e: 'openEventTypes'): void;
}>();
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">Основная информация</CardTitle>
    </CardHeader>
    <CardContent class="space-y-4">
      <div class="space-y-2">
        <Label for="history-title">Название события *</Label>
        <div class="flex items-start gap-2">
          <div class="relative min-w-0 flex-1">
            <Input
              id="history-title"
              :model-value="title"
              type="text"
              maxlength="255"
              placeholder="Название события"
              autocomplete="off"
              class="w-full"
              @update:model-value="
                (value) => {
                  emit('update:title', String(value));
                  emit('searchTitleSuggestions', String(value));
                }
              "
              @focus="emit('searchTitleSuggestions', title)"
              @blur="emit('hideTitleSuggestions')"
            />
            <div
              v-if="showTitleSuggestions && titleSuggestions.length"
              class="absolute z-20 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md"
            >
              <ul class="max-h-56 overflow-y-auto py-1 text-sm">
                <li
                  v-for="s in titleSuggestions"
                  :key="s.id"
                  class="flex items-center gap-2 px-3 py-1 hover:bg-accent"
                >
                  <span
                    class="flex-1 cursor-pointer truncate"
                    @mousedown.prevent="emit('applyTitleSuggestion', s)"
                  >
                    {{ s.name }}
                  </span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-6 w-6 text-muted-foreground hover:bg-accent/60"
                    @mousedown.prevent="emit('editTitleSuggestion', s)"
                  >
                    ✎
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-6 w-6 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    @mousedown.prevent="emit('deleteTitleSuggestion', s)"
                  >
                    ✕
                  </Button>
                </li>
              </ul>
            </div>
          </div>
          <Button
            v-if="showEventTypesButton"
            type="button"
            size="sm"
            class="shrink-0"
            @click="emit('openEventTypes')"
          >
            Виды событий
          </Button>
        </div>
      </div>

      <div
        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
        :class="{ 'sm:grid-cols-1': !dkpEnabled }"
      >
        <div class="space-y-2">
          <Label for="history-occurred-at">Дата и время *</Label>
          <Input
            id="history-occurred-at"
            :model-value="occurredAt"
            type="datetime-local"
            @update:model-value="emit('update:occurredAt', String($event))"
          />
        </div>
        <div v-if="dkpEnabled" class="space-y-2">
          <Label for="history-dkp-base">Очки ДКП за посещение</Label>
          <Input
            id="history-dkp-base"
            :model-value="dkpBasePoints"
            type="number"
            min="0"
            step="1"
            placeholder="Не задано"
            @update:model-value="emit('update:dkpBasePoints', String($event))"
          />
        </div>
      </div>

      <div class="space-y-2">
        <Label for="history-description">Описание</Label>
        <textarea
          id="history-description"
          :value="description"
          rows="4"
          class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          placeholder="Описание события (необязательно)"
          @input="emit('update:description', ($event.target as HTMLTextAreaElement).value)"
        />
      </div>
    </CardContent>
  </Card>
</template>
