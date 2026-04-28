<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/shared/ui';
import {
  eventHistoryApi,
  type EventHistoryItem,
  type EventHistoryParticipantDto,
} from '@/shared/api/eventHistoryApi';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const eventHistoryId = computed(() =>
  route.params.eventHistoryId ? Number(route.params.eventHistoryId) : null
);

const loading = ref(false);
const error = ref('');
const item = ref<EventHistoryItem | null>(null);

const lightboxOpen = ref(false);
const lightboxUrl = ref<string | null>(null);
const lightboxTitle = ref<string | null>(null);

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

function openScreenshot(url: string, title: string | null) {
  lightboxUrl.value = url;
  lightboxTitle.value = title;
  lightboxOpen.value = true;
}

function closeLightbox() {
  lightboxOpen.value = false;
  lightboxUrl.value = null;
  lightboxTitle.value = null;
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
  <div class="container py-6 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2 min-w-0">
        <Button
          variant="ghost"
          size="sm"
          class="h-9 w-9 p-0 shrink-0"
          aria-label="Назад к списку"
          @click="goBack"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M15 18l-6-6 6-6" />
          </svg>
        </Button>
        <h1 class="text-xl font-semibold truncate">
          Событие гильдии
        </h1>
      </div>
    </div>

    <Card>
      <CardHeader>
        <CardTitle class="text-base">
          {{ item?.title || 'Событие' }}
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-3 lg:flex lg:items-start lg:gap-6">
        <div class="flex-1 space-y-3">
          <p v-if="loading" class="text-sm text-muted-foreground">
            Загрузка...
          </p>
          <p v-else-if="error" class="text-sm text-destructive">
            {{ error }}
          </p>
          <template v-else-if="item">
            <p v-if="item.occurred_at" class="text-sm text-muted-foreground">
              Время проведения: {{ formatDateTime(item.occurred_at) }}
            </p>
            <p v-if="item.description" class="text-sm whitespace-pre-wrap">
              {{ item.description }}
            </p>

            <div v-if="(item.participants?.length ?? 0) > 0" class="space-y-2 text-sm">
              <Button
                variant="outline"
                size="sm"
                :disabled="exportParticipantsLoading"
                @click="exportParticipantsXlsx"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" x2="12" y1="15" y2="3" />
                </svg>
                {{ exportParticipantsLoading ? 'Формируем…' : 'Скачать Excel' }}
              </Button>
              <p
                v-if="exportParticipantsError"
                class="text-sm text-destructive"
              >
                {{ exportParticipantsError }}
              </p>
              <div class="font-medium">
                Участники ({{ item.participants?.length }}):
              </div>
              <ul class="list-disc space-y-0.5 pl-5">
                <li v-for="p in item.participants" :key="p.id">
                  <span
                    :class="
                      isExternalEventParticipant(p)
                        ? 'inline-block max-w-full rounded-sm bg-amber-500/10 px-1 py-px text-amber-900 dark:bg-amber-400/[0.12] dark:text-amber-200'
                        : ''
                    "
                  >
                    {{ p.character?.name || p.external_name }}
                  </span>
                </li>
              </ul>
            </div>
          </template>
          <p v-else class="text-sm text-muted-foreground">
            Событие не найдено.
          </p>
        </div>
        <div class="w-full lg:w-80 shrink-0 space-y-2 text-sm">
          <div class="font-medium">
            Скриншоты:
          </div>

          <div v-if="loading" class="flex flex-wrap gap-3">
            <div
              v-for="n in 4"
              :key="n"
              class="h-24 w-24 rounded border bg-muted/40"
            />
          </div>

          <div
            v-else-if="item && (item.screenshots?.length ?? 0) > 0"
            class="flex flex-wrap gap-3"
          >
            <button
              v-for="s in item.screenshots"
              :key="s.id"
              type="button"
              class="rounded border p-1 hover:bg-accent"
              @click="openScreenshot(s.url, s.title)"
            >
              <img
                :src="s.url"
                :alt="s.title || 'Скриншот'"
                width="96"
                height="96"
                loading="lazy"
                decoding="async"
                class="h-24 w-24 rounded object-cover"
              >
            </button>
          </div>

          <p v-else class="text-sm text-muted-foreground">
            Нет скриншотов.
          </p>
        </div>
      </CardContent>
    </Card>
  </div>

  <div
    v-if="lightboxOpen && lightboxUrl"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
    @click.self="closeLightbox"
  >
    <div class="max-h-[90vh] max-w-[90vw] space-y-2 text-center">
      <img
        :src="lightboxUrl"
        :alt="lightboxTitle || 'Скриншот'"
        class="max-h-[80vh] max-w-[90vw] rounded object-contain mx-auto"
      >
      <Button size="sm" variant="outline" @click="closeLightbox">
        Закрыть
      </Button>
    </div>
  </div>
</template>

