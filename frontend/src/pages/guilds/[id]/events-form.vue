<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Label } from '@/shared/ui';
import { guildsApi, type GuildRosterMember } from '@/shared/api/guildsApi';
import {
  eventHistoryApi,
  type EventHistoryItem,
  type CreateEventHistoryPayload,
  type UpdateEventHistoryPayload,
} from '@/shared/api/eventHistoryApi';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const eventHistoryId = computed(() =>
  route.params.eventHistoryId ? Number(route.params.eventHistoryId) : null
);
const isEdit = computed(() => eventHistoryId.value != null);

const loading = ref(false);
const saving = ref(false);
const error = ref('');

const roster = ref<GuildRosterMember[]>([]);
const loadingRoster = ref(false);

type Participant = { character_id?: number | null; external_name?: string | null };

const form = ref({
  title: '',
  description: '',
  occurred_at: '',
  participants: [] as Participant[],
  externalNickname: '',
  screenshots: [] as { url: string; title: string }[],
});

const guildParticipants = computed(() =>
  form.value.participants.filter((p) => p.character_id)
);
const externalParticipants = computed(() =>
  form.value.participants.filter((p) => !p.character_id && p.external_name)
);
const hasParticipants = computed(
  () => guildParticipants.value.length > 0 || externalParticipants.value.length > 0
);

function toDatetimeLocal(iso: string): string {
  const d = new Date(iso);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${day}T${h}:${min}`;
}

function fromDatetimeLocal(v: string): string | null {
  if (!v) return null;
  return new Date(v).toISOString();
}

async function loadRoster() {
  if (!guildId.value) return;
  loadingRoster.value = true;
  try {
    roster.value = await guildsApi.getGuildRoster(guildId.value);
  } catch {
    roster.value = [];
  } finally {
    loadingRoster.value = false;
  }
}

function addGuildParticipant(characterId: number) {
  if (form.value.participants.some((p) => p.character_id === characterId)) return;
  form.value.participants.push({ character_id: characterId });
}

function addExternalParticipant() {
  const nick = form.value.externalNickname.trim();
  if (!nick) return;
  form.value.participants.push({ external_name: nick });
  form.value.externalNickname = '';
}

function removeParticipant(p: Participant) {
  form.value.participants = form.value.participants.filter((x) => x !== p);
}

function addScreenshotRow() {
  form.value.screenshots.push({ url: '', title: '' });
}

function removeScreenshotRow(index: number) {
  form.value.screenshots.splice(index, 1);
}

async function loadEventIfEdit() {
  if (!isEdit.value || !guildId.value || !eventHistoryId.value) return;
  loading.value = true;
  try {
    const item: EventHistoryItem = await eventHistoryApi.get(
      guildId.value,
      eventHistoryId.value
    );
    form.value.title = item.title;
    form.value.description = item.description ?? '';
    form.value.occurred_at = item.occurred_at ? toDatetimeLocal(item.occurred_at) : '';
    form.value.participants = (item.participants ?? []).map((p) => ({
      character_id: p.character_id,
      external_name: p.character_id ? null : p.external_name,
    }));
    form.value.screenshots = (item.screenshots ?? []).map((s) => ({
      url: s.url,
      title: s.title ?? '',
    }));
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!guildId.value) return;
  error.value = '';

  if (!form.value.title.trim()) {
    error.value = 'Введите название события.';
    return;
  }

  const payloadBase: CreateEventHistoryPayload | UpdateEventHistoryPayload = {
    title: form.value.title.trim(),
    description: form.value.description.trim() || null,
    occurred_at: fromDatetimeLocal(form.value.occurred_at),
    participants: form.value.participants.map((p) => ({
      character_id: p.character_id ?? null,
      external_name: p.external_name ?? null,
    })),
    screenshots: form.value.screenshots
      .map((s, index) => ({
        url: s.url.trim(),
        title: s.title.trim() || null,
        sort_order: index,
      }))
      .filter((s) => s.url.length > 0),
  };

  saving.value = true;
  try {
    if (isEdit.value && eventHistoryId.value) {
      await eventHistoryApi.update(guildId.value, eventHistoryId.value, payloadBase);
    } else {
      await eventHistoryApi.create(guildId.value, payloadBase as CreateEventHistoryPayload);
    }
    router.push({ name: 'guild-events', params: { id: guildId.value } });
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Ошибка сохранения.';
  } finally {
    saving.value = false;
  }
}

function goBack() {
  router.push({ name: 'guild-events', params: { id: guildId.value } });
}

onMounted(async () => {
  const now = new Date();
  if (!isEdit.value) {
    form.value.occurred_at = toDatetimeLocal(now.toISOString());
  }
  await Promise.all([loadRoster(), loadEventIfEdit()]);
});
</script>

<template>
  <div class="container py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <h1 class="text-xl font-semibold">
        {{ isEdit ? 'Редактирование события' : 'Новое событие' }}
      </h1>
      <Button variant="outline" size="sm" @click="goBack">
        Назад к событиям
      </Button>
    </div>

    <div class="flex flex-col gap-4 lg:flex-row">
      <!-- Левая колонка: делаем уже на десктопе -->
      <div class="w-full lg:max-w-md lg:flex-none space-y-4">
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Основная информация</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="history-title">Название *</Label>
              <Input
                id="history-title"
                v-model="form.title"
                type="text"
                maxlength="255"
                placeholder="Название события"
              />
            </div>
            <div class="space-y-2">
              <Label for="history-occurred-at">Время проведения *</Label>
              <Input
                id="history-occurred-at"
                v-model="form.occurred_at"
                type="datetime-local"
              />
            </div>
            <div class="space-y-2">
              <Label for="history-description">Описание</Label>
              <textarea
                id="history-description"
                v-model="form.description"
                rows="4"
                class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                placeholder="Описание события (необязательно)"
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-base">Скриншоты</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3">
            <div
              v-for="(shot, index) in form.screenshots"
              :key="index"
              class="flex flex-col gap-2 rounded-md border p-2 md:flex-row md:items-center"
            >
              <div class="flex-1 space-y-1">
                <Input
                  v-model="shot.url"
                  type="url"
                  placeholder="Ссылка на скриншот *"
                />
                <Input
                  v-model="shot.title"
                  type="text"
                  placeholder="Название скриншота (необязательно)"
                />
              </div>
              <div class="flex justify-end md:items-start md:justify-center md:pl-2">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                  @click="removeScreenshotRow(index)"
                >
                  ✕
                </Button>
              </div>
            </div>
            <Button type="button" variant="outline" size="sm" @click="addScreenshotRow">
              Добавить скриншот
            </Button>
          </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
          <Button type="button" variant="outline" :disabled="saving" @click="goBack">
            Отмена
          </Button>
          <Button type="button" :disabled="saving" @click="submit">
            {{ saving ? 'Сохранение…' : isEdit ? 'Сохранить' : 'Создать' }}
          </Button>
        </div>

        <p v-if="error" class="text-sm text-destructive">
          {{ error }}
        </p>
      </div>

      <!-- Правая колонка: шире и читаемее на десктопе -->
      <div class="w-full lg:flex-1 shrink-0">
        <div class="space-y-4 lg:space-y-0 lg:flex lg:flex-row lg:gap-4">
          <Card class="lg:flex-1">
            <CardHeader>
              <CardTitle class="text-base">Участники события</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="space-y-2">
                <Label for="external-nick">Добавить стороннего участника</Label>
                <div class="flex gap-2">
                  <Input
                    id="external-nick"
                    v-model="form.externalNickname"
                    type="text"
                    placeholder="Ник участника"
                    class="flex-1"
                  />
                  <Button type="button" size="sm" @click="addExternalParticipant">
                    Добавить
                  </Button>
                </div>
              </div>

              <div class="space-y-2">
                <p class="text-xs font-medium text-muted-foreground">
                  Приняли участие:
                </p>
                <div v-if="!hasParticipants" class="text-xs text-muted-foreground">
                  Пока никто не добавлен.
                </div>
                <ul v-else class="space-y-1 text-xs">
                  <li
                    v-for="p in guildParticipants"
                    :key="`char-${p.character_id}`"
                    class="flex items-center justify-between gap-2 rounded border px-2 py-1"
                  >
                    <span>
                      {{
                        roster.find((m) => m.character_id === p.character_id)?.name ||
                        `Персонаж #${p.character_id}`
                      }}
                    </span>
                    <Button
                      type="button"
                      size="xs"
                      variant="ghost"
                      class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                      @click="removeParticipant(p)"
                    >
                      ✕
                    </Button>
                  </li>
                  <li
                    v-for="p in externalParticipants"
                    :key="`ext-${p.external_name}`"
                    class="flex items-center justify-between gap-2 rounded border px-2 py-1"
                  >
                    <span>{{ p.external_name }}</span>
                    <Button
                      type="button"
                      size="xs"
                      variant="ghost"
                      class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                      @click="removeParticipant(p)"
                    >
                      ✕
                    </Button>
                  </li>
                </ul>
              </div>
            </CardContent>
          </Card>

          <Card class="lg:flex-1">
            <CardHeader>
              <CardTitle class="text-base">Состав гильдии</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
              <p v-if="loadingRoster" class="text-xs text-muted-foreground">
                Загрузка состава...
              </p>
              <p v-else-if="!roster.length" class="text-xs text-muted-foreground">
                Нет данных о составе.
              </p>
              <ul v-else class="max-h-[320px] space-y-1 overflow-y-auto text-xs">
                <li
                  v-for="member in roster"
                  :key="member.character_id"
                  class="flex cursor-pointer items-center justify-between gap-2 rounded px-2 py-1 hover:bg-accent"
                  @click="addGuildParticipant(member.character_id)"
                >
                  <span class="truncate">{{ member.name }}</span>
                </li>
              </ul>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </div>
</template>

