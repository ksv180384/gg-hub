import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';

function parseRouteCalendarDate(raw: unknown): Date | null {
  if (typeof raw !== 'string') return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw.trim());
  if (!m) return null;
  const y = Number(m[1]);
  const mo = Number(m[2]) - 1;
  const d = Number(m[3]);
  if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(d)) return null;
  const dt = new Date(y, mo, d);
  if (dt.getFullYear() !== y || dt.getMonth() !== mo || dt.getDate() !== d) return null;
  return dt;
}
import { expandRecurringEvents, toDatetimeLocal, fromDatetimeLocal } from '@/entities/guild-calendar';
import { eventsApi, type GuildEvent } from '@/shared/api/eventsApi';
import type { ApiError } from '@/shared/api/errors';
import { guildsApi } from '@/shared/api/guildsApi';
import { charactersApi } from '@/shared/api/charactersApi';
import type { CalendarEvent } from '@/shared/ui';
import { useGuildEventsSocket } from '@/shared/lib/useGuildEventsSocket';

export function useGuildCalendar() {
  const route = useRoute();
  const guildId = computed(() => Number(route.params.id));

  const events = ref<GuildEvent[]>([]);
  const loading = ref(false);
  /** Нет членства в гильдии / закрытый календарь (403/404). */
  const calendarGuildAccessNotFound = ref(false);
  const selectedDate = ref<Date | null>(parseRouteCalendarDate(route.query.date) ?? new Date());
  const modalOpen = ref(false);
  const modalEditingId = ref<number | null>(null);
  const formLoading = ref(false);
  const formError = ref('');
  const deleteConfirmOpen = ref(false);
  const eventToDelete = ref<GuildEvent | null>(null);
  const deleteLoading = ref(false);
  const editingEvent = ref<GuildEvent | null>(null);

  const myPermissionSlugs = ref<string[]>([]);
  const guildDiscordEventStartingEnabled = ref(false);
  const myCharactersInGuild = ref<{ id: number; name: string }[]>([]);
  const loadingMyCharacters = ref(false);

  const canAddEvent = computed(() => myPermissionSlugs.value.includes('dobavliat-sobytie-kalendar'));
  const canEditEvent = computed(() => myPermissionSlugs.value.includes('redaktirovat-sobytie-kalendar'));
  const canDeleteEvent = computed(() => myPermissionSlugs.value.includes('udaliat-sobytie-kalendar'));

  const viewModalOpen = ref(false);
  const viewEvent = ref<GuildEvent | null>(null);

  const form = ref({
    character_id: 0 as number,
    title: '',
    description: '',
    starts_at: '',
    recurrence: 'once' as 'once' | 'daily' | 'weekly' | 'monthly' | 'yearly',
    recurrence_ends_at: '',
    send_discord_notification: true,
  });

  const viewRangeFrom = ref<Date | null>(null);
  const viewRangeTo = ref<Date | null>(null);

  async function fetchEvents(from: Date, to: Date) {
    if (!guildId.value) return;
    loading.value = true;
    try {
      events.value = await eventsApi.list(guildId.value, from, to);
    } catch (e) {
      events.value = [];
      const st = (e as ApiError)?.status;
      if (st === 403 || st === 404) {
        calendarGuildAccessNotFound.value = true;
      }
    } finally {
      loading.value = false;
    }
  }

  async function refreshEvent(eventId: number) {
    if (!guildId.value || !Number.isFinite(eventId) || eventId <= 0) return;
    try {
      const fresh = await eventsApi.get(guildId.value, eventId);
      const idx = events.value.findIndex((e) => e.id === fresh.id);
      if (idx !== -1) {
        events.value = [
          ...events.value.slice(0, idx),
          { ...events.value[idx], ...fresh },
          ...events.value.slice(idx + 1),
        ];
      } else {
        // если событие вне текущего диапазона, оставляем как есть
      }
      if (editingEvent.value?.id === fresh.id) editingEvent.value = fresh;
      if (viewEvent.value?.id === fresh.id) viewEvent.value = fresh;
    } catch {
      // ignore
    }
  }

  function onViewRange(from: Date, to: Date) {
    viewRangeFrom.value = from;
    viewRangeTo.value = to;
    fetchEvents(from, to);
  }

  async function loadCalendarPage() {
    if (!guildId.value) return;

    events.value = [];
    myPermissionSlugs.value = [];
    guildDiscordEventStartingEnabled.value = false;
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
    calendarGuildAccessNotFound.value = false;

    try {
      const guild = await guildsApi.getGuildForSettings(guildId.value);
      myPermissionSlugs.value = guild.my_permission_slugs ?? [];
      guildDiscordEventStartingEnabled.value = guild.discord_notify_event_starting === true;
    } catch (e) {
      myPermissionSlugs.value = [];
      guildDiscordEventStartingEnabled.value = false;
      const st = (e as ApiError)?.status;
      if (st === 403 || st === 404) {
        calendarGuildAccessNotFound.value = true;
        return;
      }
    }

    if (calendarGuildAccessNotFound.value) {
      return;
    }

    if (viewRangeFrom.value && viewRangeTo.value) {
      await fetchEvents(viewRangeFrom.value, viewRangeTo.value);
    }
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

  const eventsForSelectedDay = computed(() => {
    if (!selectedDate.value) return [];
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
      const [guild, rosterRes] = await Promise.all([
        guildsApi.getGuild(guildId.value),
        guildsApi.getGuildRoster(guildId.value),
      ]);
      const rosterIds = new Set(rosterRes.members.map((r) => r.character_id));
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
      send_discord_notification: true,
    };
    formError.value = '';
    modalOpen.value = true;
  }

  async function openEvent(event: CalendarEvent) {
    if (!guildId.value) return;
    const full = events.value.find((e) => e.id === event.id) ?? (await eventsApi.get(guildId.value, event.id));
    viewEvent.value = full;
    viewModalOpen.value = true;
  }

  /** Открыть модалку редактирования (кнопка «карандаш» у пользователя с правом на редактирование). */
  async function openEventEditModal(event: CalendarEvent) {
    if (!guildId.value || !canEditEvent.value) return;
    viewModalOpen.value = false;
    const full = events.value.find((e) => e.id === event.id) ?? (await eventsApi.get(guildId.value, event.id));
    editingEvent.value = full;
    modalEditingId.value = full.id;
    form.value = {
      character_id: full.created_by_character_id ?? 0,
      title: full.title,
      description: full.description ?? '',
      starts_at: toDatetimeLocal(full.starts_at),
      recurrence: (full.recurrence ?? 'once') as 'once' | 'daily' | 'weekly' | 'monthly' | 'yearly',
      recurrence_ends_at: full.recurrence_ends_at ? toDatetimeLocal(full.recurrence_ends_at) : '',
      send_discord_notification: full.send_discord_notification ?? true,
    };
    formError.value = '';
    modalOpen.value = true;
  }

  function closeModal() {
    modalOpen.value = false;
    modalEditingId.value = null;
    editingEvent.value = null;
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
      send_discord_notification: form.value.send_discord_notification,
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

  useGuildEventsSocket({
    guildId,
    onChanged: ({ eventId }) => {
      void refreshEvent(eventId);
    },
  });

  function deleteFromEditForm() {
    const ev = editingEvent.value;
    closeModal();
    if (ev) askDelete(ev);
  }

  watch(guildId, () => {
    loadCalendarPage();
  }, { immediate: true });

  watch(
    () => route.query.date,
    (q) => {
      const d = parseRouteCalendarDate(q);
      if (d) selectedDate.value = d;
    },
  );

  return {
    guildId,
    events,
    loading,
    calendarGuildAccessNotFound,
    selectedDate,
    modalOpen,
    modalEditingId,
    formLoading,
    formError,
    deleteConfirmOpen,
    eventToDelete,
    deleteLoading,
    editingEvent,
    myPermissionSlugs,
    myCharactersInGuild,
    loadingMyCharacters,
    canAddEvent,
    canEditEvent,
    canDeleteEvent,
    guildDiscordEventStartingEnabled,
    viewModalOpen,
    viewEvent,
    form,
    calendarEvents,
    eventsForSelectedDay,
    selectedDateLabel,
    onViewRange,
    openCreateModal,
    openEvent,
    openEventEditModal,
    closeModal,
    submitForm,
    askDelete,
    confirmDelete,
    handleSelectDate,
    handleClickEvent,
    deleteFromEditForm,
  };
}
