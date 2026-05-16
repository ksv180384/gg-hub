<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, Button, Tooltip } from '@/shared/ui';
import type {
  EventHistoryItem,
  EventHistoryParticipantDto,
} from '@/shared/api/eventHistoryApi';
import { calculateEventParticipantDkpPoints } from '@/shared/lib/calculateEventParticipantDkpPoints';

const props = defineProps<{
  item: EventHistoryItem;
  exportParticipantsLoading: boolean;
  exportParticipantsError: string;
  isExternalEventParticipant: (p: EventHistoryParticipantDto) => boolean;
}>();

const guildParticipantsForDkp = computed(() =>
  (props.item.participants ?? [])
    .filter((p) => p.character_id != null)
    .map((p) => ({
      character_id: p.character_id,
      dkp_coefficient: p.dkp?.coefficient ?? 1,
      dkp_points_override: p.dkp?.points_override ?? null,
    }))
);

function participantDkpPoints(p: EventHistoryParticipantDto): number | null {
  if (!props.item.dkp) {
    return null;
  }
  return calculateEventParticipantDkpPoints(
    props.item.dkp.base_points,
    {
      character_id: p.character_id,
      dkp_coefficient: p.dkp?.coefficient ?? 1,
      dkp_points_override: p.dkp?.points_override ?? null,
    },
    {
      distributeTotal: props.item.dkp.distribute_to_participants ?? false,
      guildParticipants: guildParticipantsForDkp.value,
    }
  );
}

const emit = defineEmits<{
  (e: 'exportParticipantsXlsx'): void;
}>();
</script>

<template>
  <Card>
    <CardContent class="space-y-6 pt-6">
      <section class="space-y-3">
        <div
          v-if="(item.participants?.length ?? 0) > 0"
          class="flex flex-wrap items-center justify-between gap-2"
        >
          <Tooltip
            :content="`Приняли участие (${item.participants?.length ?? 0})`"
            side="top"
          >
            <span
              class="inline-flex cursor-default items-center gap-1.5 text-sm font-semibold text-foreground"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
              >
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
              <span class="tabular-nums">{{ item.participants?.length }}</span>
            </span>
          </Tooltip>
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
                v-if="item.dkp && participantDkpPoints(p) != null"
                class="shrink-0 text-right text-sm tabular-nums"
              >
                <span class="font-semibold text-foreground">
                  +{{ participantDkpPoints(p) }} очк.
                </span>
                <span
                  v-if="p.dkp?.points_override != null"
                  class="mt-0.5 block text-xs text-muted-foreground"
                >
                  коррекция
                </span>
                <span
                  v-else-if="
                    item.dkp.distribute_to_participants &&
                    p.dkp?.coefficient != null &&
                    p.dkp.coefficient !== 1
                  "
                  class="mt-0.5 block text-xs text-muted-foreground"
                >
                  коэф. {{ p.dkp.coefficient }}
                </span>
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
