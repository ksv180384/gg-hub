<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button, Input, Label, SelectRoot, SelectTrigger, SelectValue, SelectContent, SelectItem, Spinner } from '@/shared/ui';
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
          Список заявок доступен только участникам гильдии с правом просмотра заявок.
        </p>
        <Button
          variant="outline"
          @click="router.push({ name: 'guild-application-form', params: { id: String(guildId) } })"
        >
          Подать заявку в гильдию
        </Button>
      </template>

      <template v-else-if="error">
        <p class="text-destructive">{{ error }}</p>
        <Button variant="outline" class="mt-4" @click="router.push({ name: 'guild-show', params: { id: String(guildId) } })">
          К гильдии
        </Button>
      </template>

      <template v-else>
        <div class="mb-4 grid gap-3 sm:grid-cols-2">
          <div class="space-y-1.5">
            <Label for="app-filter-name" class="text-xs text-muted-foreground">Имя персонажа</Label>
            <Input
              id="app-filter-name"
              v-model="nameFilter"
              placeholder="Поиск по имени…"
            />
          </div>

          <div class="space-y-1.5">
            <Label for="app-filter-status" class="text-xs text-muted-foreground">Статус</Label>
            <SelectRoot v-model="statusFilter">
              <SelectTrigger id="app-filter-status" class="w-full">
                <SelectValue placeholder="Статус" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </SelectItem>
              </SelectContent>
            </SelectRoot>
          </div>
        </div>

        <div v-if="filtersLoading" class="flex justify-center py-6">
          <Spinner class="h-6 w-6" />
        </div>

        <p v-else-if="applications.length === 0" class="text-muted-foreground">
          {{ hasActiveFilters ? 'Ничего не найдено по заданным фильтрам.' : 'Заявок пока нет.' }}
        </p>
        <ul v-else class="space-y-2">
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
              <p class="text-sm text-muted-foreground">
                {{ statusLabel(app.status) }}
                <template v-if="app.created_at">
                  · {{ new Date(app.created_at).toLocaleDateString('ru-RU') }}
                </template>
              </p>
            </div>
          </li>
        </ul>
      </template>
    </div>

  </div>
</template>
