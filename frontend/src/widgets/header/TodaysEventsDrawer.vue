<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { Badge, Sheet, Separator } from '@/shared/ui';
import { useTodaysGuildEvents, type TodaysGuildEventOccurrence } from '@/features/todays-guild-events';
import { eventsApi } from '@/shared/api/eventsApi';

const router = useRouter();
/** reactive() снимает Ref-обёртки со свойств — иначе v-model:open уходит в Sheet как Ref, а не boolean */
const todays = reactive(useTodaysGuildEvents());
const decliningId = reactive<{ id: number | null }>({ id: null });

const badgeText = computed(() => {
  const n = todays.count;
  if (n <= 0) return '';
  if (n > 9) return '9+';
  return String(n);
});

function timeLabel(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function subtitle(e: TodaysGuildEventOccurrence): string {
  const parts: string[] = [];
  if (e.game?.name) parts.push(e.game.name);
  if (e.guild?.name) parts.push(e.guild.name);
  return parts.join(' · ');
}

function openGuildCalendar(e: TodaysGuildEventOccurrence) {
  const gid = e.guild?.id ?? e.guild_id;
  if (!Number.isFinite(gid) || gid <= 0) return;
  todays.open = false;
  router.push({ name: 'guild-calendar', params: { id: gid } });
}

async function decline(e: TodaysGuildEventOccurrence) {
  const gid = e.guild?.id ?? e.guild_id;
  if (!Number.isFinite(gid) || gid <= 0) return;
  if (decliningId.id !== null) return;
  decliningId.id = e.id;
  try {
    await eventsApi.decline(gid, e.id);
    await todays.reload();
  } finally {
    decliningId.id = null;
  }
}
</script>

<template>
  <Sheet v-model:open="todays.open" side="right">
    <template #trigger>
      <button
        type="button"
        class="relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        aria-label="События сегодня"
        title="События сегодня"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-[1.125rem] w-[1.125rem]">
          <path d="M8 2v4"/><path d="M16 2v4"/><path d="M3 10h18"/><path d="M4 6h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/>
        </svg>
        <Badge
          v-if="badgeText"
          class="absolute -right-1 -top-1 min-w-5 justify-center px-1.5 text-[0.65rem] leading-4"
          variant="secondary"
        >
          {{ badgeText }}
        </Badge>
      </button>
    </template>

    <template #toolbar-start>События сегодня</template>

    <div class="flex min-h-0 flex-1 flex-col">
      <div v-if="todays.loading && todays.occurrences.length === 0" class="text-sm text-muted-foreground">Загрузка…</div>
      <div v-else-if="todays.error" class="text-sm text-destructive">{{ todays.error }}</div>
      <div v-else-if="todays.occurrences.length === 0" class="text-sm text-muted-foreground">
        Сегодня нет событий.
      </div>

      <div v-else class="min-h-0 flex-1 overflow-y-auto -mx-6 px-6">
        <ul class="space-y-3">
          <li v-for="e in todays.occurrences" :key="`${e.id}-${e.occurrence_starts_at}`">
            <div class="flex items-start gap-2 rounded-md border border-border/60 p-3">
              <button
                type="button"
                class="group flex min-w-0 flex-1 items-start gap-3 text-left"
                @click="openGuildCalendar(e)"
              >
                <div class="shrink-0 pt-0.5 text-xs font-medium text-muted-foreground tabular-nums">
                  {{ timeLabel(e.occurrence_starts_at) }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="truncate text-sm font-medium group-hover:underline">{{ e.title }}</div>
                  <div class="mt-0.5 truncate text-xs text-muted-foreground">
                    {{ subtitle(e) }}
                  </div>
                </div>
              </button>
              <button
                type="button"
                class="shrink-0 inline-flex h-7 w-[5.75rem] items-center justify-center rounded-md border border-input bg-background px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
                :disabled="decliningId.id === e.id"
                @click="decline(e)"
                :title="e.my_declined ? 'Отменить «Не смогу»' : 'Не смогу принять участие'"
              >
                <svg
                  v-if="decliningId.id === e.id"
                  class="h-4 w-4 animate-spin text-muted-foreground"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"
                  />
                </svg>
                <span v-else>
                  {{ e.my_declined ? 'Отменить' : 'Не смогу' }}
                </span>
              </button>
            </div>
          </li>
        </ul>
        <Separator class="my-4" />
        <p class="text-xs text-muted-foreground">
          Показаны события из всех ваших гильдий.
        </p>
      </div>
    </div>
  </Sheet>
</template>

