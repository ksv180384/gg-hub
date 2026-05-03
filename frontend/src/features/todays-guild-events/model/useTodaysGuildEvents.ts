import { computed, ref, watch } from 'vue';
import { expandRecurringEvents } from '@/entities/guild-calendar';
import { userCalendarEventsApi, type UserCalendarEventItem } from '@/shared/api/userCalendarEventsApi';
import { useAuthStore } from '@/stores/auth';

export type TodaysGuildEventOccurrence = UserCalendarEventItem & {
  occurrence_starts_at: string;
  occurrence_ends_at: string | null;
};

function getLocalDayRange(d: Date): { from: Date; to: Date } {
  const from = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0, 0);
  const to = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59, 999);
  return { from, to };
}

export function useTodaysGuildEvents() {
  const auth = useAuthStore();

  const open = ref(false);
  const loading = ref(false);
  const error = ref('');
  const baseEvents = ref<UserCalendarEventItem[]>([]);

  async function reload() {
    if (!auth.isAuthenticated) return;
    loading.value = true;
    error.value = '';
    try {
      baseEvents.value = await userCalendarEventsApi.listToday();
    } catch (e: unknown) {
      baseEvents.value = [];
      error.value = e instanceof Error ? e.message : 'Ошибка загрузки.';
    } finally {
      loading.value = false;
    }
  }

  const occurrences = computed<TodaysGuildEventOccurrence[]>(() => {
    const { from, to } = getLocalDayRange(new Date());
    const expanded = expandRecurringEvents(baseEvents.value as never, from, to);
    const byId = new Map<number, UserCalendarEventItem>();
    for (const e of baseEvents.value) byId.set(e.id, e);

    const out: TodaysGuildEventOccurrence[] = [];
    for (const occ of expanded) {
      const base = byId.get(occ.id);
      if (!base) continue;
      out.push({
        ...base,
        occurrence_starts_at: occ.starts_at,
        occurrence_ends_at: occ.ends_at,
      });
    }

    out.sort((a, b) => a.occurrence_starts_at.localeCompare(b.occurrence_starts_at));
    return out;
  });

  const count = computed(() => occurrences.value.length);

  watch(
    () => auth.isAuthenticated,
    (isAuth) => {
      if (!isAuth) {
        open.value = false;
        baseEvents.value = [];
        error.value = '';
        loading.value = false;
        return;
      }
      void reload();
    },
    { immediate: true }
  );

  watch(open, (v) => {
    if (v && auth.isAuthenticated) void reload();
  });

  return {
    open,
    loading,
    error,
    occurrences,
    count,
    reload,
  };
}

