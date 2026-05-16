<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { BackIconButton } from '@/shared/ui';
import {
  eventHistoryApi,
  type EventHistoryItem,
  type EventHistoryParticipantDto,
} from '@/shared/api/eventHistoryApi';
import EventsFormTabsHeader from './ui/EventsFormTabsHeader.vue';
import EventsShowInformationTab from './ui/EventsShowInformationTab.vue';
import EventsShowParticipantsTab from './ui/EventsShowParticipantsTab.vue';
import EventsShowScreenshotsTab from './ui/EventsShowScreenshotsTab.vue';
import type { EventsFormTabId } from './events-form-types';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const eventHistoryId = computed(() =>
  route.params.eventHistoryId ? Number(route.params.eventHistoryId) : null
);

const loading = ref(false);
const error = ref('');
const item = ref<EventHistoryItem | null>(null);
const activeTab = ref<EventsFormTabId>('information');

const exportParticipantsLoading = ref(false);
const exportParticipantsError = ref('');

function isExternalEventParticipant(p: EventHistoryParticipantDto): boolean {
  return p.character_id == null;
}

function formatDateTime(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

async function loadEvent() {
  if (!guildId.value || !eventHistoryId.value) return;
  loading.value = true;
  error.value = '';
  try {
    item.value = await eventHistoryApi.get(guildId.value, eventHistoryId.value);
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить событие.';
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.push({ name: 'guild-events', params: { id: guildId.value } });
}

async function exportParticipantsXlsx() {
  const current = item.value;
  const list = current?.participants;
  if (!current || !list?.length) return;
  exportParticipantsLoading.value = true;
  exportParticipantsError.value = '';
  try {
    const { exportEventParticipantsToXlsx } = await import(
      '@/shared/lib/eventHistoryParticipantsXlsx'
    );
    await exportEventParticipantsToXlsx({
      eventTitle: current.title,
      participants: list,
    });
  } catch (e: unknown) {
    exportParticipantsError.value =
      e instanceof Error ? e.message : 'Не удалось выгрузить участников.';
  } finally {
    exportParticipantsLoading.value = false;
  }
}

onMounted(loadEvent);
</script>

<template>
  <div class="container overflow-x-hidden py-6 md:py-8">
    <div class="fixed top-[100px] right-8 z-30 md:hidden">
      <BackIconButton
        aria-label="К списку событий"
        title="К списку событий"
        @click="goBack"
      />
    </div>

    <div
      class="flex flex-col gap-8 lg:grid lg:grid-cols-[minmax(0,42rem)_minmax(0,1fr)] lg:gap-10"
    >
      <div class="min-w-0 space-y-4">
        <div class="relative flex flex-col md:flex-row md:items-start md:gap-3">
          <div class="sticky top-[100px] z-30 hidden shrink-0 self-start md:block">
            <BackIconButton
              aria-label="К списку событий"
              title="К списку событий"
              @click="goBack"
            />
          </div>

          <div class="min-w-0 w-full flex-1 space-y-4">
            <p v-if="loading" class="text-sm text-muted-foreground">Загрузка...</p>
            <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
            <p v-else-if="!item" class="text-sm text-muted-foreground">
              Событие не найдено.
            </p>

            <template v-else>
              <h1 class="text-xl font-semibold">
                {{ item.title }}
              </h1>

              <EventsFormTabsHeader v-model:active-tab="activeTab" />

              <EventsShowInformationTab
                v-show="activeTab === 'information'"
                :item="item"
                :format-date-time="formatDateTime"
              />

              <EventsShowParticipantsTab
                v-show="activeTab === 'participants'"
                :item="item"
                :export-participants-loading="exportParticipantsLoading"
                :export-participants-error="exportParticipantsError"
                :is-external-event-participant="isExternalEventParticipant"
                @export-participants-xlsx="exportParticipantsXlsx"
              />

              <EventsShowScreenshotsTab
                v-show="activeTab === 'screenshots'"
                :item="item"
                :loading="loading"
              />
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
