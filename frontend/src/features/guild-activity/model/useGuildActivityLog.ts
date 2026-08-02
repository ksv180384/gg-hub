import { computed, onMounted, ref, shallowRef, watch } from 'vue';
import type { DateRange } from 'radix-vue';
import { useRoute } from 'vue-router';
import {
  guildActivityApi,
  type GuildActivityCategory,
  type GuildActivityLog,
} from '@/shared/api/guildActivityApi';

export const GUILD_ACTIVITY_CATEGORY_OPTIONS: {
  value: GuildActivityCategory;
  label: string;
}[] = [
  { value: 'auction', label: 'Аукцион' },
  { value: 'roulette', label: 'Рулетка' },
  { value: 'storage', label: 'Хранилище' },
  { value: 'members', label: 'Состав' },
  { value: 'access', label: 'Роли и права' },
  { value: 'guild', label: 'Информация' },
  { value: 'journal', label: 'Журнал' },
  { value: 'events', label: 'События' },
];

const CATEGORY_ALL = '__all__';

export function formatGuildActivityDate(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}

export function guildActivityCategoryLabel(category: GuildActivityCategory): string {
  return GUILD_ACTIVITY_CATEGORY_OPTIONS.find((option) => option.value === category)?.label
    ?? category;
}

export function useGuildActivityLog() {
  const route = useRoute();
  const guildId = computed(() => Number(route.params.id));

  const logs = ref<GuildActivityLog[]>([]);
  const loading = ref(false);
  const error = ref('');
  const currentPage = ref(1);
  const lastPage = ref(1);
  const total = ref(0);

  const searchFilter = ref('');
  const actorNameFilter = ref('');
  const dateRangeFilter = shallowRef<DateRange>({
    start: undefined,
    end: undefined,
  });
  const createdFromFilter = computed(
    () => dateRangeFilter.value.start?.toString() ?? '',
  );
  const createdToFilter = computed(
    () => dateRangeFilter.value.end?.toString() ?? '',
  );
  const categoryFilter = ref<string>(CATEGORY_ALL);

  const hasActiveFilters = computed(
    () =>
      searchFilter.value.trim() !== ''
      || actorNameFilter.value.trim() !== ''
      || createdFromFilter.value !== ''
      || createdToFilter.value !== ''
      || categoryFilter.value !== CATEGORY_ALL,
  );

  const categoryOptions = computed(() => [
    { value: CATEGORY_ALL, label: 'Все разделы' },
    ...GUILD_ACTIVITY_CATEGORY_OPTIONS,
  ]);

  function resetFilters() {
    searchFilter.value = '';
    actorNameFilter.value = '';
    dateRangeFilter.value = {
      start: undefined,
      end: undefined,
    };
    categoryFilter.value = CATEGORY_ALL;
  }

  async function loadPage(page = 1) {
    if (!Number.isFinite(guildId.value) || guildId.value <= 0) return;

    loading.value = true;
    error.value = '';

    try {
      const result = await guildActivityApi.list(guildId.value, {
        page,
        search: searchFilter.value.trim() || undefined,
        actor_name: actorNameFilter.value.trim() || undefined,
        created_from: createdFromFilter.value || undefined,
        created_to: createdToFilter.value || undefined,
        category:
          categoryFilter.value === CATEGORY_ALL
            ? undefined
            : (categoryFilter.value as GuildActivityCategory),
      });
      logs.value = result.data;
      currentPage.value = result.meta.current_page;
      lastPage.value = result.meta.last_page;
      total.value = result.meta.total;
    } catch (caught: unknown) {
      logs.value = [];
      error.value = caught instanceof Error
        ? caught.message
        : 'Не удалось загрузить историю гильдии.';
    } finally {
      loading.value = false;
    }
  }

  let filterTimer: ReturnType<typeof setTimeout> | null = null;

  function scheduleFilterReload() {
    if (filterTimer) clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
      void loadPage(1);
    }, 300);
  }

  onMounted(() => {
    void loadPage();
  });

  watch(guildId, () => {
    resetFilters();
    void loadPage();
  });

  watch(
    [createdFromFilter, createdToFilter, categoryFilter],
    () => {
      void loadPage(1);
    },
  );

  watch(
    [searchFilter, actorNameFilter],
    scheduleFilterReload,
  );

  return {
    guildId,
    logs,
    loading,
    error,
    currentPage,
    lastPage,
    total,
    searchFilter,
    actorNameFilter,
    dateRangeFilter,
    categoryFilter,
    categoryOptions,
    categoryAll: CATEGORY_ALL,
    hasActiveFilters,
    resetFilters,
    loadPage,
  };
}
