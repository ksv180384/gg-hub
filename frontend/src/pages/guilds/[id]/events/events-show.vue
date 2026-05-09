<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardHeader, CardTitle, CardContent, Button, BackIconButton, Input, Label, LightboxImage } from '@/shared/ui';
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

const exportParticipantsLoading = ref(false);
const exportParticipantsError = ref('');

const dkpSaving = ref(false);
const dkpError = ref('');

function isExternalEventParticipant(p: EventHistoryParticipantDto): boolean {
  return p.character_id == null;
}

function calcParticipantDkpPoints(base: number | null, coef: number, override: number | null): number | null {
  if (override != null) return override;
  if (base == null) return null;
  return Math.round(base * (Number.isFinite(coef) ? coef : 1));
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
    // гарантируем наличие dkp-объекта у участников для v-model
    if (item.value?.dkp && Array.isArray(item.value.participants)) {
      item.value.participants = item.value.participants.map((p) => ({
        ...p,
        dkp: p.dkp ?? { coefficient: 1, points_override: null },
      }));
    }
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить событие.';
  } finally {
    loading.value = false;
  }
}

async function saveDkp() {
  if (!guildId.value || !eventHistoryId.value || !item.value) return;
  if (!item.value.dkp) return;
  dkpSaving.value = true;
  dkpError.value = '';
  try {
    const payload = {
      dkp_base_points: item.value.dkp.base_points,
      participants: (item.value.participants ?? []).map((p) => ({
        character_id: p.character_id,
        external_name: p.character_id ? null : p.external_name,
        dkp_coefficient: p.dkp?.coefficient ?? 1,
        dkp_points_override: p.dkp?.points_override ?? null,
      })),
    };
    const updated = await eventHistoryApi.update(guildId.value, eventHistoryId.value, payload);
    // нормализуем dkp у участников так же, как после loadEvent
    if (updated?.dkp && Array.isArray(updated.participants)) {
      updated.participants = updated.participants.map((p) => ({
        ...p,
        dkp: p.dkp ?? { coefficient: 1, points_override: null },
      }));
    }
    item.value = updated;
  } catch (e: unknown) {
    dkpError.value = e instanceof Error ? e.message : 'Не удалось сохранить ДКП.';
  } finally {
    dkpSaving.value = false;
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
  <div class="container py-6 overflow-x-hidden">
    <!-- Mobile: одна плавающая кнопка справа -->
    <div class="fixed top-[100px] right-8 z-30 md:hidden">
      <BackIconButton
        aria-label="К списку событий"
        title="К списку событий"
        @click="goBack"
      />
    </div>

    <div class="relative flex flex-col md:flex-row md:items-start md:gap-3">
      <!-- Desktop: стрелка слева от контента -->
      <div class="sticky top-[100px] z-30 hidden shrink-0 self-start md:block">
        <BackIconButton
          aria-label="К списку событий"
          title="К списку событий"
          @click="goBack"
        />
      </div>

      <div class="min-w-0 w-full flex-1 space-y-4">
        <h1 class="text-xl font-semibold truncate">
          Событие гильдии
        </h1>

        <Card class="max-w-2xl md:mx-0 mx-auto">
      <CardHeader>
        <CardTitle class="text-base">
          {{ item?.title || 'Событие' }}
        </CardTitle>
      </CardHeader>
      <CardContent class="min-w-0 space-y-3 lg:flex lg:items-start lg:gap-6">
        <div class="min-w-0 flex-1 space-y-3">
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

            <div v-if="item.dkp" class="space-y-2 rounded-md border p-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="font-medium">ДКП</div>
                <Button variant="outline" size="sm" :disabled="dkpSaving" @click="saveDkp">
                  {{ dkpSaving ? 'Сохранение…' : 'Сохранить ДКП' }}
                </Button>
              </div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="space-y-1">
                  <Label for="dkp-base">Базовые очки *</Label>
                  <Input
                    id="dkp-base"
                    v-model.number="item.dkp.base_points"
                    type="number"
                    min="0"
                    placeholder="Например 10"
                  />
                </div>
              </div>
              <p v-if="dkpError" class="text-sm text-destructive">{{ dkpError }}</p>

              <div v-if="(item.participants?.length ?? 0) > 0" class="space-y-2 text-sm">
                <div class="font-medium">Коэффициенты и очки</div>
                <ul class="space-y-2">
                  <li v-for="p in item.participants" :key="p.id" class="rounded border p-2">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                      <div class="min-w-0">
                        <div class="truncate font-medium">
                          {{ p.character?.name || p.external_name }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                          Итог:
                          {{
                            calcParticipantDkpPoints(
                              item.dkp.base_points ?? null,
                              p.dkp?.coefficient ?? 1,
                              p.dkp?.points_override ?? null
                            ) ?? '—'
                          }}
                        </div>
                      </div>
                      <div class="grid grid-cols-2 gap-2 sm:w-[260px]">
                        <div class="space-y-1">
                          <Label :for="`dkp-coef-${p.id}`">Коэф.</Label>
                          <Input
                            :id="`dkp-coef-${p.id}`"
                            v-model.number="p.dkp!.coefficient"
                            type="number"
                            step="0.1"
                            min="0"
                          />
                        </div>
                        <div class="space-y-1">
                          <Label :for="`dkp-ovr-${p.id}`">Override</Label>
                          <Input
                            :id="`dkp-ovr-${p.id}`"
                            v-model.number="p.dkp!.points_override"
                            type="number"
                            min="0"
                            placeholder="—"
                          />
                        </div>
                      </div>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

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
        <div class="min-w-0 w-full lg:w-80 shrink-0 space-y-2 text-sm">
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
            <LightboxImage
              v-for="s in item.screenshots"
              :key="s.id"
              :src="s.url"
              :alt="s.title || 'Скриншот'"
              :title="s.title || 'Скриншот'"
              button-class="rounded border p-1 hover:bg-accent transition-colors"
              img-class="h-24 w-24 rounded object-cover"
            />
          </div>

          <p v-else class="text-sm text-muted-foreground">
            Нет скриншотов.
          </p>
        </div>
      </CardContent>
    </Card>
      </div>
    </div>
  </div>
</template>

