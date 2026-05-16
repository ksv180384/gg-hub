import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { guildDkpApi, type GuildDkpLedgerEntry, type GuildDkpLedgerSource } from '@/shared/api/guildDkpApi';
import { guildBankApi } from '@/shared/api/guildBankApi';
import {
  eventHistoryTitlesApi,
  type EventHistoryTitleDto,
} from '@/shared/api/eventHistoryTitlesApi';

export function formatLedgerDescription(entry: GuildDkpLedgerEntry): string {
  if (entry.source === 'event') {
    return entry.event_history?.title ?? 'Событие';
  }
  if (entry.source === 'bank_grant' || entry.source === 'bank_grant_revoke') {
    return entry.guild_bank_item?.name ?? 'Предмет из банка';
  }
  return entry.reason?.trim() || 'Ручная корректировка';
}

const EVENT_TITLE_FILTER_ALL = '__all__';
const SOURCE_FILTER_ALL = '__all__';

const LEDGER_SOURCE_FILTER_OPTIONS: { value: GuildDkpLedgerSource; label: string }[] = [
  { value: 'event', label: 'Событие' },
  { value: 'manual', label: 'Ручная корректировка' },
  { value: 'bank_grant', label: 'Выдача из банка' },
  { value: 'bank_grant_revoke', label: 'Отмена выдачи' },
];

export function formatLedgerSourceLabel(source: GuildDkpLedgerSource): string {
  return LEDGER_SOURCE_FILTER_OPTIONS.find((option) => option.value === source)?.label ?? source;
}

export function useGuildDkpLedger() {
  const route = useRoute();
  const guildId = computed(() => Number(route.params.id));

  const loading = ref(false);
  const filtersLoading = ref(false);
  const hasLoadedOnce = ref(false);
  const error = ref('');
  const entries = ref<GuildDkpLedgerEntry[]>([]);
  const ledgerPage = ref(1);
  const ledgerLastPage = ref(1);
  const ledgerTotal = ref(0);
  const ledgerLoadingMore = ref(false);
  const dkpEnabled = ref(false);
  const eventTitles = ref<EventHistoryTitleDto[]>([]);

  const LEDGER_PER_PAGE = 50;

  const userNameFilter = ref('');
  const occurredFromFilter = ref('');
  const occurredToFilter = ref('');
  const eventTitleFilter = ref<string>(EVENT_TITLE_FILTER_ALL);
  const sourceFilter = ref<string>(SOURCE_FILTER_ALL);

  const eventTitleSelectOptions = computed(() =>
    [...eventTitles.value]
      .sort((a, b) => a.name.localeCompare(b.name, 'ru'))
      .map((title) => ({ value: String(title.id), label: title.name })),
  );

  const ledgerSourceSelectOptions = computed(() => LEDGER_SOURCE_FILTER_OPTIONS);

  const ledgerExtraFiltersActive = computed(
    () =>
      !!occurredFromFilter.value ||
      !!occurredToFilter.value ||
      eventTitleFilter.value !== EVENT_TITLE_FILTER_ALL ||
      sourceFilter.value !== SOURCE_FILTER_ALL,
  );

  const ledgerActiveFiltersCount = computed(() => {
    let count = 0;
    if (userNameFilter.value.trim().length > 0) count += 1;
    if (occurredFromFilter.value) count += 1;
    if (occurredToFilter.value) count += 1;
    if (eventTitleFilter.value !== EVENT_TITLE_FILTER_ALL) count += 1;
    if (sourceFilter.value !== SOURCE_FILTER_ALL) count += 1;
    return count;
  });

  const hasActiveFilters = computed(() => ledgerActiveFiltersCount.value > 0);

  const canLoadMoreLedger = computed(
    () => ledgerPage.value < ledgerLastPage.value && !loading.value && !filtersLoading.value,
  );

  function resetLedgerFilters() {
    userNameFilter.value = '';
    occurredFromFilter.value = '';
    occurredToFilter.value = '';
    eventTitleFilter.value = EVENT_TITLE_FILTER_ALL;
    sourceFilter.value = SOURCE_FILTER_ALL;
  }

  async function loadEventTitles() {
    try {
      eventTitles.value = await eventHistoryTitlesApi.list({ limit: 0 });
    } catch {
      eventTitles.value = [];
    }
  }

  function buildLedgerParams(page: number) {
    const eventHistoryTitleId =
      eventTitleFilter.value !== EVENT_TITLE_FILTER_ALL
        ? Number(eventTitleFilter.value)
        : undefined;

    const source =
      sourceFilter.value !== SOURCE_FILTER_ALL
        ? (sourceFilter.value as GuildDkpLedgerSource)
        : undefined;

    return {
      page,
      per_page: LEDGER_PER_PAGE,
      occurred_from: occurredFromFilter.value || undefined,
      occurred_to: occurredToFilter.value || undefined,
      user_name: userNameFilter.value.trim() || undefined,
      ...(eventHistoryTitleId != null && Number.isFinite(eventHistoryTitleId)
        ? { event_history_title_id: eventHistoryTitleId }
        : {}),
      ...(source ? { source } : {}),
    };
  }

  async function loadPage(mode: 'initial' | 'filters' = 'initial') {
    if (!guildId.value) return;

    const isInitial = mode === 'initial';
    if (isInitial) {
      loading.value = true;
      entries.value = [];
      ledgerPage.value = 1;
    } else {
      filtersLoading.value = true;
      entries.value = [];
      ledgerPage.value = 1;
    }
    error.value = '';

    try {
      if (isInitial) {
        const context = await guildBankApi.getPageContext(guildId.value);
        dkpEnabled.value = context.dkp_enabled ?? false;
        if (!dkpEnabled.value) {
          entries.value = [];
          error.value = 'Система ДКП отключена в этой гильдии.';
          return;
        }
      } else if (!dkpEnabled.value) {
        entries.value = [];
        return;
      }

      const result = await guildDkpApi.listLedger(guildId.value, buildLedgerParams(1));
      entries.value = result.data;
      ledgerPage.value = result.meta.current_page;
      ledgerLastPage.value = result.meta.last_page;
      ledgerTotal.value = result.meta.total;
    } catch (e: unknown) {
      entries.value = [];
      error.value = e instanceof Error ? e.message : 'Не удалось загрузить историю ДКП.';
    } finally {
      if (isInitial) loading.value = false;
      filtersLoading.value = false;
      hasLoadedOnce.value = true;
    }
  }

  async function loadMoreLedger() {
    if (!guildId.value || !dkpEnabled.value || !canLoadMoreLedger.value || ledgerLoadingMore.value) {
      return;
    }

    ledgerLoadingMore.value = true;
    try {
      const nextPage = ledgerPage.value + 1;
      const result = await guildDkpApi.listLedger(guildId.value, buildLedgerParams(nextPage));
      entries.value = [...entries.value, ...result.data];
      ledgerPage.value = result.meta.current_page;
      ledgerLastPage.value = result.meta.last_page;
      ledgerTotal.value = result.meta.total;
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Не удалось загрузить историю ДКП.';
    } finally {
      ledgerLoadingMore.value = false;
    }
  }

  let filtersTimer: ReturnType<typeof setTimeout> | null = null;
  function scheduleReloadWithFilters() {
    if (filtersTimer) clearTimeout(filtersTimer);
    filtersTimer = setTimeout(() => {
      void loadPage('filters');
    }, 300);
  }

  onMounted(() => {
    void loadEventTitles();
    void loadPage('initial');
  });

  watch(guildId, () => {
    resetLedgerFilters();
    void loadPage('initial');
  });

  watch([occurredFromFilter, occurredToFilter, eventTitleFilter, sourceFilter], () => {
    if (!hasLoadedOnce.value || !dkpEnabled.value) return;
    void loadPage('filters');
  });

  watch(userNameFilter, () => {
    if (!hasLoadedOnce.value || !dkpEnabled.value) return;
    scheduleReloadWithFilters();
  });

  return {
    guildId,
    loading,
    filtersLoading,
    hasLoadedOnce,
    error,
    entries,
    dkpEnabled,
    userNameFilter,
    occurredFromFilter,
    occurredToFilter,
    eventTitleFilter,
    sourceFilter,
    ledgerSourceSelectOptions,
    sourceFilterAll: SOURCE_FILTER_ALL,
    eventTitleSelectOptions,
    eventTitleFilterAll: EVENT_TITLE_FILTER_ALL,
    ledgerExtraFiltersActive,
    ledgerActiveFiltersCount,
    hasActiveFilters,
    resetLedgerFilters,
    loadPage,
    loadMoreLedger,
    canLoadMoreLedger,
    ledgerLoadingMore,
    ledgerTotal,
    formatLedgerDescription,
  };
}
