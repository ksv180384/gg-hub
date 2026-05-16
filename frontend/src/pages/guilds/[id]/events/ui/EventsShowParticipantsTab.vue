<script setup lang="ts">
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/shared/ui';
import type {
  EventHistoryItem,
  EventHistoryParticipantDto,
} from '@/shared/api/eventHistoryApi';

defineProps<{
  item: EventHistoryItem;
  exportParticipantsLoading: boolean;
  exportParticipantsError: string;
  isExternalEventParticipant: (p: EventHistoryParticipantDto) => boolean;
}>();

const emit = defineEmits<{
  (e: 'exportParticipantsXlsx'): void;
}>();
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">Участники события</CardTitle>
    </CardHeader>
    <CardContent class="space-y-6">
      <section class="space-y-3">
        <div
          v-if="(item.participants?.length ?? 0) > 0"
          class="flex flex-wrap items-center justify-between gap-2"
        >
          <p class="text-sm font-semibold">
            Приняли участие ({{ item.participants?.length }})
          </p>
          <Button
            variant="outline"
            size="sm"
            :disabled="exportParticipantsLoading"
            class="gap-2"
            @click="emit('exportParticipantsXlsx')"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="size-4 shrink-0"
              aria-hidden="true"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" x2="12" y1="15" y2="3" />
            </svg>
            {{ exportParticipantsLoading ? 'Формируем…' : 'Скачать Excel' }}
          </Button>
        </div>

        <p v-if="exportParticipantsError" class="text-sm text-destructive">
          {{ exportParticipantsError }}
        </p>

        <p
          v-if="!(item.participants?.length ?? 0)"
          class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
        >
          Участники не указаны.
        </p>

        <ul v-else class="space-y-2">
          <li
            v-for="p in item.participants"
            :key="p.id"
            class="rounded-lg border px-3 py-2.5 text-sm"
            :class="
              isExternalEventParticipant(p)
                ? 'border-amber-500/40 bg-amber-500/5'
                : 'bg-card'
            "
          >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <span class="font-medium">
                {{ p.character?.name || p.external_name }}
              </span>
              <span
                v-if="item.dkp"
                class="text-xs text-muted-foreground tabular-nums"
              >
                <template v-if="p.dkp?.points_override != null">
                  {{ p.dkp.points_override }} очк. (коррекция)
                </template>
                <template v-else-if="p.dkp?.coefficient != null && p.dkp.coefficient !== 1">
                  коэф. {{ p.dkp.coefficient }}
                </template>
              </span>
            </div>
            <span
              v-if="isExternalEventParticipant(p)"
              class="mt-0.5 block text-xs text-muted-foreground"
            >
              Сторонний участник
            </span>
          </li>
        </ul>
      </section>
    </CardContent>
  </Card>
</template>
