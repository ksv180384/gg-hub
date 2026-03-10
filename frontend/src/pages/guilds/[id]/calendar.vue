<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Calendar,
  Button,
  Input,
  Label,
  ConfirmDialog,
} from '@/shared/ui';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import { useRoute } from 'vue-router';
import { eventsApi, type GuildEvent, type CreateEventPayload } from '@/shared/api/eventsApi';
import { guildsApi } from '@/shared/api/guildsApi';
import { charactersApi } from '@/shared/api/charactersApi';
import type { CalendarEvent } from '@/shared/ui';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

const events = ref<GuildEvent[]>([]);
const loading = ref(false);
const selectedDate = ref<Date | null>(new Date());
const modalOpen = ref(false);
const modalEditingId = ref<number | null>(null);
const formLoading = ref(false);
const formError = ref('');
const deleteConfirmOpen = ref(false);
const eventToDelete = ref<GuildEvent | null>(null);
const deleteLoading = ref(false);
const editingEvent = ref<GuildEvent | null>(null);

/** Права текущего пользователя в гильдии (из настроек гильдии). */
const myPermissionSlugs = ref<string[]>([]);

/** Персонажи текущего пользователя в этой гильдии (для выбора «от имени кого» создаём событие). */
const myCharactersInGuild = ref<{ id: number; name: string }[]>([]);
const loadingMyCharacters = ref(false);

const canAddEvent = computed(() => myPermissionSlugs.value.includes('dobavliat-sobytie-kalendar'));
const canEditEvent = computed(() => myPermissionSlugs.value.includes('redaktirovat-sobytie-kalendar'));
const canDeleteEvent = computed(() => myPermissionSlugs.value.includes('udaliat-sobytie-kalendar'));

/** Модалка только просмотра (название и описание) для пользователей без права редактирования. */
const viewModalOpen = ref(false);
const viewEvent = ref<GuildEvent | null>(null);

const form = ref({
  character_id: 0 as number,
  title: '',
  description: '',
  starts_at: '',
  recurrence: 'once' as 'once' | 'daily' | 'weekly' | 'monthly' | 'yearly',
  recurrence_ends_at: '',
});

function toDatetimeLocal(iso: string): string {
  const d = new Date(iso);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${day}T${h}:${min}`;
}

function fromDatetimeLocal(s: string): string {
  if (!s) return '';
  return new Date(s).toISOString();
}

function getMonthRange(viewDate: Date) {
  const start = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
  const end = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
  return { from: start, to: end };
}

async function fetchEvents(from: Date, to: Date) {
  if (!guildId.value) return;
  loading.value = true;
  try {
    events.value = await eventsApi.list(guildId.value, from, to);
  } catch {
    events.value = [];
  } finally {
    loading.value = false;
  }
}

/** Диапазон отображаемого месяца (для развёртки повторяющихся событий). */
const viewRangeFrom = ref<Date | null>(null);
const viewRangeTo = ref<Date | null>(null);

function onViewRange(from: Date, to: Date) {
  viewRangeFrom.value = from;
  viewRangeTo.value = to;
  fetchEvents(from, to);
}

async function loadCalendarPage() {
  if (!guildId.value) return;

  events.value = [];
  myPermissionSlugs.value = [];
  myCharactersInGuild.value = [];
  loadingMyCharacters.value = false;
  modalOpen.value = false;
  modalEditingId.value = null;
  viewModalOpen.value = false;
  viewEvent.value = null;
  deleteConfirmOpen.value = false;
  eventToDelete.value = null;
  editingEvent.value = null;
  formError.value = '';

  try {
    const guild = await guildsApi.getGuildForSettings(guildId.value);
    myPermissionSlugs.value = guild.my_permission_slugs ?? [];
  } catch {
    myPermissionSlugs.value = [];
  }

  if (viewRangeFrom.value && viewRangeTo.value) {
    await fetchEvents(viewRangeFrom.value, viewRangeTo.value);
  }
}

/**
 * Разворачивает повторяющиеся события в отдельные вхождения по дням в заданном диапазоне.
 */
function expandRecurringEvents(
  list: GuildEvent[],
  rangeFrom: Date | null,
  rangeTo: Date | null
): CalendarEvent[] {
  if (!rangeFrom || !rangeTo) {
    return list.map((e) => ({
      id: e.id,
      title: e.title,
      starts_at: e.starts_at,
      ends_at: e.ends_at,
    }));
  }
  const from = new Date(rangeFrom.getFullYear(), rangeFrom.getMonth(), rangeFrom.getDate());
  const to = new Date(rangeTo.getFullYear(), rangeTo.getMonth(), rangeTo.getDate());
  const result: CalendarEvent[] = [];

  function dayKey(d: Date): string {
    return (
      d.getFullYear() +
      '-' +
      String(d.getMonth() + 1).padStart(2, '0') +
      '-' +
      String(d.getDate()).padStart(2, '0')
    );
  }
  function timePart(iso: string): string {
    const i = iso.indexOf('T');
    return i >= 0 ? iso.slice(i + 1) : '00:00:00.000Z';
  }
  function occurrenceStartsEnds(dayDate: Date, e: GuildEvent): { starts_at: string; ends_at: string } {
    const key = dayKey(dayDate);
    return {
      starts_at: key + 'T' + timePart(e.starts_at),
      ends_at: key + 'T' + (e.ends_at ? timePart(e.ends_at) : '23:59:59.999Z'),
    };
  }

  for (const e of list) {
    const start = new Date(e.starts_at);
    const end = e.ends_at ? new Date(e.ends_at) : new Date(start);
    const recurEnd = e.recurrence_ends_at ? new Date(e.recurrence_ends_at) : null;
    const recurrence = e.recurrence ?? 'once';

    const startDate = new Date(start.getFullYear(), start.getMonth(), start.getDate());
    let effectiveEnd: Date;
    if (recurrence === 'once') {
      effectiveEnd = new Date(end.getFullYear(), end.getMonth(), end.getDate());
      if (startDate <= to && effectiveEnd >= from) {
        result.push({ id: e.id, title: e.title, starts_at: e.starts_at, ends_at: e.ends_at });
      }
      continue;
    }
    effectiveEnd = recurEnd
      ? new Date(recurEnd.getFullYear(), recurEnd.getMonth(), recurEnd.getDate())
      : new Date(to);
    if (effectiveEnd > to) effectiveEnd = new Date(to);
    if (startDate > effectiveEnd) continue;

    if (recurrence === 'daily') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setDate(d.getDate() + 1);
      }
    } else if (recurrence === 'weekly') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setDate(d.getDate() + 7);
      }
    } else if (recurrence === 'monthly') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setMonth(d.getMonth() + 1);
      }
    } else if (recurrence === 'yearly') {
      const d = new Date(startDate);
      while (d <= effectiveEnd) {
        if (d >= from) {
          const { starts_at: s, ends_at: ed } = occurrenceStartsEnds(d, e);
          result.push({ id: e.id, title: e.title, starts_at: s, ends_at: ed });
        }
        d.setFullYear(d.getFullYear() + 1);
      }
    }
  }
  return result;
}

const calendarEvents = computed((): CalendarEvent[] =>
  expandRecurringEvents(events.value, viewRangeFrom.value, viewRangeTo.value)
);

function selectedDateKey(): string {
  if (!selectedDate.value) return '';
  return (
    selectedDate.value.getFullYear() +
    '-' +
    String(selectedDate.value.getMonth() + 1).padStart(2, '0') +
    '-' +
    String(selectedDate.value.getDate()).padStart(2, '0')
  );
}

/** События на выбранную дату (с учётом повторений): уникальные по id, полные GuildEvent. */
const eventsForSelectedDay = computed(() => {
  if (!selectedDate.value) return [];
  const key = selectedDateKey();
  const expanded = expandRecurringEvents(events.value, selectedDate.value, selectedDate.value);
  const idsOnDay = new Set(expanded.map((o) => o.id));
  return events.value.filter((e) => idsOnDay.has(e.id));
});

const selectedDateLabel = computed(() => {
  if (!selectedDate.value) return '';
  return selectedDate.value.toLocaleDateString('ru-RU', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  });
});

async function loadMyCharactersInGuild() {
  if (!guildId.value || myCharactersInGuild.value.length) return;
  loadingMyCharacters.value = true;
  try {
    const [guild, roster] = await Promise.all([
      guildsApi.getGuild(guildId.value),
      guildsApi.getGuildRoster(guildId.value),
    ]);
    const rosterIds = new Set(roster.map((r) => r.character_id));
    const allChars = await charactersApi.getCharacters(guild.game_id);
    myCharactersInGuild.value = allChars
      .filter((c) => rosterIds.has(c.id))
      .map((c) => ({ id: c.id, name: c.name }));
  } catch {
    myCharactersInGuild.value = [];
  } finally {
    loadingMyCharacters.value = false;
  }
}

async function openCreateModal() {
  modalEditingId.value = null;
  await loadMyCharactersInGuild();
  const base = selectedDate.value ?? new Date();
  form.value = {
    character_id: myCharactersInGuild.value[0]?.id ?? 0,
    title: '',
    description: '',
    starts_at: toDatetimeLocal(base.toISOString()),
    recurrence: 'once',
    recurrence_ends_at: '',
  };
  formError.value = '';
  modalOpen.value = true;
}

async function openEvent(event: CalendarEvent) {
  if (!guildId.value) return;
  const full = events.value.find((e) => e.id === event.id) ?? (await eventsApi.get(guildId.value, event.id));
  if (canEditEvent.value) {
    editingEvent.value = full;
    modalEditingId.value = full.id;
    form.value = {
      character_id: full.created_by_character_id ?? 0,
      title: full.title,
      description: full.description ?? '',
      starts_at: toDatetimeLocal(full.starts_at),
      recurrence: (full.recurrence ?? 'once') as 'once' | 'daily' | 'weekly' | 'monthly' | 'yearly',
      recurrence_ends_at: full.recurrence_ends_at ? toDatetimeLocal(full.recurrence_ends_at) : '',
    };
    formError.value = '';
    modalOpen.value = true;
  } else {
    viewEvent.value = full;
    viewModalOpen.value = true;
  }
}

function closeModal() {
  modalOpen.value = false;
  modalEditingId.value = null;
  editingEvent.value = null;
  // Обновляем события в календаре с учётом текущего видимого месяца
  if (viewRangeFrom.value && viewRangeTo.value) {
    fetchEvents(viewRangeFrom.value, viewRangeTo.value);
  }
}

async function submitForm() {
  formError.value = '';
  if (!form.value.title.trim()) {
    formError.value = 'Введите название события.';
    return;
  }
  const basePayload = {
    title: form.value.title.trim(),
    description: form.value.description.trim() || null,
    starts_at: fromDatetimeLocal(form.value.starts_at) || new Date().toISOString(),
    ends_at: null,
    recurrence: form.value.recurrence === 'once' ? null : form.value.recurrence,
    recurrence_ends_at: form.value.recurrence_ends_at
      ? fromDatetimeLocal(form.value.recurrence_ends_at)
      : null,
  };

  formLoading.value = true;
  try {
    if (modalEditingId.value != null) {
      await eventsApi.update(guildId.value!, modalEditingId.value, basePayload);
    } else {
      if (!form.value.character_id) {
        formError.value = 'Выберите персонажа от имени которого создаётся событие.';
        formLoading.value = false;
        return;
      }
      await eventsApi.create(guildId.value!, {
        ...basePayload,
        character_id: form.value.character_id,
      });
    }
    closeModal();
  } catch (err: unknown) {
    formError.value = err instanceof Error ? err.message : 'Ошибка сохранения.';
  } finally {
    formLoading.value = false;
  }
}

function askDelete(ev: GuildEvent) {
  eventToDelete.value = ev;
  deleteConfirmOpen.value = true;
}

async function confirmDelete() {
  if (!eventToDelete.value || !guildId.value) return;
  deleteLoading.value = true;
  try {
    await eventsApi.delete(guildId.value, eventToDelete.value.id);
    deleteConfirmOpen.value = false;
    eventToDelete.value = null;
    if (viewRangeFrom.value && viewRangeTo.value) {
      fetchEvents(viewRangeFrom.value, viewRangeTo.value);
    }
  } finally {
    deleteLoading.value = false;
  }
}

function handleSelectDate(date: Date) {
  selectedDate.value = date;
}

function handleClickEvent(event: CalendarEvent) {
  openEvent(event);
}

watch(guildId, () => {
  loadCalendarPage();
}, { immediate: true });
</script>

<template>
  <div class="container py-6">
    <div class="flex flex-col gap-6 lg:flex-row">
      <Card class="flex-1">
        <CardHeader>
          <CardTitle>Календарь событий</CardTitle>
        </CardHeader>
        <CardContent>
          <Calendar
            :events="calendarEvents"
            :selected-date="selectedDate"
            :show-new-event-button="canAddEvent"
            @view-range="onViewRange"
            @select-date="handleSelectDate"
            @new-event="openCreateModal"
            @click-event="handleClickEvent"
          />
        </CardContent>
      </Card>

      <Card v-if="selectedDate" class="w-full lg:w-80 shrink-0">
        <CardHeader class="pb-2">
          <CardTitle class="text-base">События на {{ selectedDateLabel }}</CardTitle>
          <Button
            v-if="canAddEvent"
            variant="outline"
            size="sm"
            class="mt-2 w-full"
            @click="openCreateModal"
          >
            Добавить событие
          </Button>
        </CardHeader>
        <CardContent class="pt-0">
          <ul v-if="eventsForSelectedDay.length" class="space-y-2">
            <li
              v-for="ev in eventsForSelectedDay"
              :key="ev.id"
              class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm"
            >
              <button
                type="button"
                class="min-w-0 flex-1 truncate text-left font-medium hover:underline"
                @click="openEvent({ id: ev.id, title: ev.title, starts_at: ev.starts_at, ends_at: ev.ends_at })"
              >
                {{ ev.title }}
              </button>
              <Button
                v-if="canDeleteEvent"
                variant="ghost"
                size="icon"
                class="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
                aria-label="Удалить"
                @click="askDelete(ev)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
              </Button>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">Нет событий на эту дату.</p>
        </CardContent>
      </Card>
    </div>

    <!-- Модалка создания/редактирования -->
    <DialogRoot :open="modalOpen" @update:open="(v: boolean) => !v && closeModal()">
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            {{ modalEditingId != null ? 'Редактировать событие' : 'Новое событие' }}
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Заполните данные события. Поля со звёздочкой обязательны.
          </DialogDescription>

          <form class="flex flex-col gap-4" @submit.prevent="submitForm">
            <div v-if="modalEditingId == null" class="space-y-2">
              <Label for="event-character">Персонаж (от имени кого создаётся) *</Label>
              <select
                id="event-character"
                v-model.number="form.character_id"
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :disabled="loadingMyCharacters"
              >
                <option :value="0">
                  {{ loadingMyCharacters ? 'Загрузка…' : myCharactersInGuild.length ? '— Выберите персонажа —' : 'Нет персонажей в гильдии' }}
                </option>
                <option
                  v-for="c in myCharactersInGuild"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </option>
              </select>
            </div>
            <div class="space-y-2">
              <Label for="event-title">Название *</Label>
              <Input
                id="event-title"
                v-model="form.title"
                type="text"
                placeholder="Название события"
                maxlength="255"
                class="w-full"
              />
            </div>
            <div class="space-y-2">
              <Label for="event-desc">Описание</Label>
              <textarea
                id="event-desc"
                v-model="form.description"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Описание (необязательно)"
              />
            </div>
            <div class="space-y-2">
              <Label for="event-start">Начало *</Label>
              <Input
                id="event-start"
                v-model="form.starts_at"
                type="datetime-local"
                class="w-full"
              />
            </div>
            <div class="space-y-2">
              <Label for="event-recurrence">Повторение</Label>
              <select
                id="event-recurrence"
                v-model="form.recurrence"
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                <option value="once">Один раз</option>
                <option value="daily">Ежедневно</option>
                <option value="weekly">Еженедельно</option>
                <option value="monthly">Ежемесячно</option>
                <option value="yearly">Ежегодно</option>
              </select>
            </div>
            <div v-if="form.recurrence !== 'once'" class="space-y-2">
              <Label for="event-recurrence-end">Повторять до</Label>
              <Input
                id="event-recurrence-end"
                v-model="form.recurrence_ends_at"
                type="datetime-local"
                class="w-full"
              />
            </div>

            <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>

            <div class="flex flex-wrap justify-between gap-2 pt-2">
              <div v-if="modalEditingId != null && canDeleteEvent">
                <Button
                  type="button"
                  variant="ghost"
                  class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                  :disabled="formLoading"
                  @click="() => { const ev = editingEvent.value; closeModal(); if (ev) askDelete(ev); }"
                >
                  Удалить
                </Button>
              </div>
              <div class="flex gap-2 ms-auto">
                <Button type="button" variant="outline" :disabled="formLoading" @click="closeModal">
                  Отмена
                </Button>
                <Button type="submit" :disabled="formLoading">
                  {{ formLoading ? 'Сохранение…' : modalEditingId != null ? 'Сохранить' : 'Создать' }}
                </Button>
              </div>
            </div>
          </form>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>

    <!-- Модалка просмотра (только название и описание) для пользователей без права редактирования -->
    <DialogRoot :open="viewModalOpen" @update:open="(v: boolean) => !v && (viewModalOpen = false)">
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            {{ viewEvent?.title ?? 'Событие' }}
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground whitespace-pre-wrap">
            {{ viewEvent?.description || 'Нет описания.' }}
          </DialogDescription>
          <div class="flex justify-end pt-2">
            <Button variant="outline" @click="viewModalOpen = false">
              Закрыть
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>

    <ConfirmDialog
      v-model:open="deleteConfirmOpen"
      title="Удалить событие?"
      description="Событие будет удалено без возможности восстановления."
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="deleteLoading"
      confirm-variant="destructive"
      @confirm="confirmDelete"
    />
  </div>
</template>
