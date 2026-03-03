<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/shared/ui';
import { eventHistoryApi, type EventHistoryItem } from '@/shared/api/eventHistoryApi';

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

onMounted(loadEvent);
</script>

<template>
  <div class="container py-6 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-xl font-semibold">
        Событие гильдии
      </h1>
      <Button variant="outline" size="sm" @click="goBack">
        Назад к списку
      </Button>
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

            <div v-if="(item.participants?.length ?? 0) > 0" class="space-y-1 text-sm">
              <div class="font-medium">
                Участники ({{ item.participants?.length }}):
              </div>
              <ul class="list-disc pl-4 space-y-0.5">
                <li
                  v-for="p in item.participants"
                  :key="p.id"
                >
                  {{ p.character?.name || p.external_name }}
                </li>
              </ul>
            </div>
          </template>
          <p v-else class="text-sm text-muted-foreground">
            Событие не найдено.
          </p>
        </div>
        <div
          v-if="item && (item.screenshots?.length ?? 0) > 0"
          class="w-full lg:w-80 shrink-0 space-y-2 text-sm"
        >
          <div class="font-medium">
            Скриншоты:
          </div>
          <div class="flex flex-wrap gap-3">
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
                class="max-h-[320px] max-w-[320px] rounded object-cover"
              >
            </button>
          </div>
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

