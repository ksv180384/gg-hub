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
  // статус меняется редко — без debounce
  loadApplications('filters');
});

watch([nameFilter], () => {
  // ввод имени — с debounce, чтобы не спамить сервер
  scheduleReloadWithFilters();
});
</script>

<template>
  <NotFoundPage v-if="applicationsGuildNotFound" />
  <div v-else class="container py-6 max-w-2xl mx-auto">
    <div class="text-xl font-semibold pb-4">Заявки и приглашения</div>

    <div>
      <div v-if="loading && !hasLoadedOnce" class="flex justify-center py-12">
        <Spinner class="h-8 w-8" />
      </div>

      <template v-else-if="noAccess">
        <p class="text-muted-foreground mb-4">
          У вас недостаточно прав для просмотра заявок в гильдию.
        </p>
      </template>

      <template v-else-if="error">
        <p class="text-destructive">{{ error }}</p>
        <Button variant="outline" class="mt-4" @click="router.push({ name: 'guild-show', params: { id: String(guildId) } })">
          К гильдии
        </Button>
      </template>

      <template v-else>
        <ResponsiveFiltersToolbar
          v-model:name="nameFilter"
          class="mb-6"
          name-label="Имя персонажа"
          name-placeholder="Поиск по имени..."
          :extra-filters-active="applicationsExtraFiltersActive"
          extra-filters-title="Статус"
          popover-trigger-title="Статус"
          popover-trigger-aria-label="Открыть фильтр: статус"
          reset-button-title="Сбросить фильтр"
          reset-button-aria-label="Сбросить фильтр"
          name-mobile-input-id="guild-app-filter-name-mobile"
          name-desktop-input-id="guild-app-filter-name-desktop"
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
                trigger-class="min-h-8 w-full"
              />
            </div>
          </template>
          <template #desktop-filters>
            <div class="grid w-36 shrink-0 gap-1.5 sm:w-40">
              <Label for="guild-app-filter-status-desktop">Статус</Label>
              <Select
                id="guild-app-filter-status-desktop"
                v-model="statusFilter"
                :options="statusSelectOptions"
                placeholder="Все статусы"
                trigger-class="min-h-8 w-full"
              />
            </div>
          </template>
        </ResponsiveFiltersToolbar>

        <div class="relative min-h-[120px]">
          <div
            v-if="filtersLoading"
            class="absolute left-1/2 top-0 z-10 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-muted/70 px-2 py-0.5"
            aria-busy="true"
            aria-live="polite"
          >
            <Spinner class="h-3 w-3 shrink-0 text-muted-foreground" />
            <span class="text-xs text-muted-foreground">Загрузка…</span>
          </div>

          <p v-if="!filtersLoading && applications.length === 0" class="text-muted-foreground">
            {{ hasActiveFilters ? 'Ничего не найдено по заданным фильтрам.' : 'Заявок пока нет.' }}
          </p>
          <ul v-else-if="applications.length > 0" class="space-y-2">
          <li
            v-for="app in applications"
            :key="app.id"
            role="button"
            tabindex="0"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 hover:bg-muted/50 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @click="goToApplication(app.id)"
            @keydown.enter.prevent="goToApplication(app.id)"
            @keydown.space.prevent="goToApplication(app.id)"
          >
            <div class="min-w-0">
              <p class="font-medium">{{ app.character?.name ?? '—' }}</p>
              <p class="text-sm">
                <span
                  :class="
                    app.status === 'pending'
                      ? 'font-medium text-green-600 dark:text-green-400'
                      : 'text-muted-foreground'
                  "
                >
                  {{ statusLabel(app.status) }}
                </span>
                <template v-if="app.created_at">
                  <span class="text-muted-foreground">
                    · {{ new Date(app.created_at).toLocaleDateString('ru-RU') }}
                  </span>
                </template>
              </p>
            </div>
          </li>
        </ul>
        </div>
      </template>
    </div>

  </div>
</template>
