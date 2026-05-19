<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button, Label, Select, Spinner, type SelectOption } from '@/shared/ui';
import { ResponsiveFiltersToolbar } from '@/widgets/responsive-filters-toolbar';
import { guildsApi, type GuildApplicationItem } from '@/shared/api/guildsApi';
import NotFoundPage from '@/pages/not-found/index.vue';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));

const applications = ref<GuildApplicationItem[]>([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const loading = ref(true);
const error = ref<string | null>(null);
/** Участник без права просмотра заявок (403). */
const noAccess = ref(false);
/** Нет членства в гильдии и т.п. (404 guild.member), URL не меняем. */
const applicationsGuildNotFound = ref(false);

const statusFilter = ref<'all' | GuildApplicationItem['status']>('all');
const nameFilter = ref('');
const filtersLoading = ref(false);
const hasLoadedOnce = ref(false);

const hasActiveFilters = computed(() => statusFilter.value !== 'all' || nameFilter.value.trim().length > 0);

function statusLabel(status: string) {
  if (status === 'pending') return 'На рассмотрении';
  if (status === 'invitation') return 'Приглашение';
  if (status === 'approved') return 'Принята';
  if (status === 'rejected') return 'Отклонена';
  if (status === 'revoked') return 'Приглашение отозвано';
  if (status === 'withdrawn') return 'Отозвана';
  return status;
}

function statusClass(status: string) {
  if (status === 'pending' || status === 'invitation') {
    return 'text-green-700 dark:text-green-400';
  }
  if (status === 'rejected' || status === 'revoked' || status === 'withdrawn') {
    return 'text-muted-foreground';
  }
  return 'text-foreground';
}

function goToApplication(appId: number) {
  router.push({ name: 'guild-application-show', params: { id: String(guildId.value), applicationId: String(appId) } });
}

const STATUS_OPTIONS: { value: 'all' | GuildApplicationItem['status']; label: string }[] = [
  { value: 'all', label: 'Все статусы' },
  { value: 'pending', label: statusLabel('pending') },
  { value: 'invitation', label: statusLabel('invitation') },
  { value: 'approved', label: statusLabel('approved') },
  { value: 'rejected', label: statusLabel('rejected') },
  { value: 'revoked', label: statusLabel('revoked') },
  { value: 'withdrawn', label: statusLabel('withdrawn') },
];

const statusSelectOptions = computed<SelectOption[]>(() =>
  STATUS_OPTIONS.map((o) => ({ value: o.value, label: o.label })),
);

const applicationsExtraFiltersActive = computed(() => statusFilter.value !== 'all');

function resetApplicationsFilters() {
  nameFilter.value = '';
  statusFilter.value = 'all';
}

function formatApplicationDate(value: string | null | undefined): string {
  if (!value) return '';
  return new Date(value).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

async function loadApplications(mode: 'initial' | 'filters' = 'initial') {
  const isInitial = mode === 'initial';
  if (isInitial) {
    applications.value = [];
    meta.value = { current_page: 1, last_page: 1, per_page: 20, total: 0 };
    loading.value = true;
  } else {
    filtersLoading.value = true;
  }
  error.value = null;
  noAccess.value = false;
  applicationsGuildNotFound.value = false;

  if (!guildId.value || Number.isNaN(guildId.value)) {
    error.value = 'Неверная ссылка.';
    loading.value = false;
    return;
  }
  try {
    const status = statusFilter.value === 'all' ? undefined : statusFilter.value;
    const character_name = nameFilter.value.trim() || undefined;

    const result = await guildsApi.getGuildApplications(guildId.value, {
      page: 1,
      per_page: 20,
      ...(status ? { status } : {}),
      ...(character_name ? { character_name } : {}),
    });
    applications.value = result.applications;
    meta.value = result.meta;
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) {
      applicationsGuildNotFound.value = true;
      error.value = null;
    } else if (err.status === 403) {
      noAccess.value = true;
      error.value = null;
    } else {
      error.value = err.message ?? 'Не удалось загрузить заявки.';
    }
  } finally {
    if (isInitial) loading.value = false;
    filtersLoading.value = false;
    hasLoadedOnce.value = true;
  }
}

let filtersTimer: ReturnType<typeof setTimeout> | null = null;
function scheduleReloadWithFilters() {
  if (filtersTimer) clearTimeout(filtersTimer);
  filtersTimer = setTimeout(() => {
    loadApplications('filters');
  }, 300);
}

watch(guildId, () => {
  loadApplications('initial');
}, { immediate: true });

watch([statusFilter], () => {
  loadApplications('filters');
});

watch([nameFilter], () => {
  scheduleReloadWithFilters();
});
</script>

<template>
  <NotFoundPage v-if="applicationsGuildNotFound" />
  <div v-else class="max-w-[720px] space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0">
        <h1 class="text-xl font-semibold tracking-normal text-foreground">
          Заявки и приглашения
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Входящие заявки, отправленные приглашения и решения по составу
        </p>
      </div>
    </div>

    <div v-if="loading && !hasLoadedOnce" class="flex justify-center rounded-lg border border-border bg-card py-12">
      <Spinner class="h-8 w-8" />
    </div>

    <template v-else-if="noAccess">
      <p class="rounded-lg border border-border bg-card px-4 py-5 text-sm text-muted-foreground">
        У вас недостаточно прав для просмотра заявок в гильдию.
      </p>
    </template>

    <template v-else-if="error">
      <div class="rounded-lg border border-border bg-card px-4 py-5">
        <p class="text-sm text-destructive">{{ error }}</p>
        <Button
          variant="outline"
          class="mt-4"
          @click="router.push({ name: 'guild-show', params: { id: String(guildId) } })"
        >
          К гильдии
        </Button>
      </div>
    </template>

    <template v-else>
      <ResponsiveFiltersToolbar
        v-model:name="nameFilter"
        name-label="Имя персонажа"
        name-placeholder="Поиск по имени..."
        :extra-filters-active="applicationsExtraFiltersActive"
        :active-filters-count="applicationsExtraFiltersActive ? 1 : 0"
        extra-filters-title="Статус"
        popover-trigger-title="Статус"
        popover-trigger-aria-label="Открыть фильтр: статус"
        reset-button-title="Сбросить фильтр"
        reset-button-aria-label="Сбросить фильтр"
        name-mobile-input-id="guild-app-filter-name-mobile"
        name-desktop-input-id="guild-app-filter-name-desktop"
        card-class="border-0 bg-transparent shadow-none"
        card-content-class="p-0"
        desktop-row-class="items-end"
        desktop-name-wrap-class="min-w-0 flex-1"
        @reset="resetApplicationsFilters"
      >
        <template #extra-filters>
          <div class="grid gap-1.5">
            <Label for="guild-app-filter-status-mobile">Статус</Label>
            <Select
              id="guild-app-filter-status-mobile"
              v-model="statusFilter"
              :options="statusSelectOptions"
              placeholder="Все статусы"
              trigger-class="h-8 w-full"
            />
          </div>
        </template>
        <template #desktop-filters>
          <div class="grid w-44 shrink-0 gap-1.5">
            <Label for="guild-app-filter-status-desktop">Статус</Label>
            <Select
              id="guild-app-filter-status-desktop"
              v-model="statusFilter"
              :options="statusSelectOptions"
              placeholder="Все статусы"
              trigger-class="h-8 w-full"
            />
          </div>
        </template>
      </ResponsiveFiltersToolbar>

      <div class="relative min-h-[120px]">
        <div
          v-if="filtersLoading"
          class="absolute left-1/2 top-2 z-10 flex -translate-x-1/2 items-center gap-1.5 rounded-md border border-border bg-popover px-2.5 py-1 shadow-lg shadow-black/5"
          aria-busy="true"
          aria-live="polite"
        >
          <Spinner class="h-3 w-3 shrink-0 text-muted-foreground" />
          <span class="text-xs text-muted-foreground">Загрузка...</span>
        </div>

        <p
          v-if="!filtersLoading && applications.length === 0"
          class="rounded-lg border border-border bg-card px-4 py-5 text-sm text-muted-foreground"
        >
          {{ hasActiveFilters ? 'Ничего не найдено по заданным фильтрам.' : 'Заявок пока нет.' }}
        </p>

        <ul
          v-else-if="applications.length > 0"
          class="overflow-hidden rounded-lg border border-border bg-card shadow-sm"
          :class="{ 'opacity-60': filtersLoading }"
        >
          <li
            v-for="app in applications"
            :key="app.id"
            role="button"
            tabindex="0"
            class="group flex items-center justify-between gap-3 border-b border-border/80 px-4 py-3 transition-colors last:border-b-0 hover:bg-accent/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/15"
            @click="goToApplication(app.id)"
            @keydown.enter.prevent="goToApplication(app.id)"
            @keydown.space.prevent="goToApplication(app.id)"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-foreground group-hover:text-primary">
                {{ app.character?.name ?? '—' }}
              </p>
              <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs leading-5">
                <span class="font-medium" :class="statusClass(app.status)">
                  {{ statusLabel(app.status) }}
                </span>
                <template v-if="app.created_at">
                  <span class="text-muted-foreground/45" aria-hidden="true">·</span>
                  <span class="text-muted-foreground">
                    {{ formatApplicationDate(app.created_at) }}
                  </span>
                </template>
              </p>
            </div>
            <svg
              class="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-foreground"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="m9 18 6-6-6-6" />
            </svg>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
