<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  Button,
  Input,
  Label,
} from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { guildsApi } from '@/shared/api/guildsApi';
import {
  eventHistoryApi,
  type EventHistoryItem,
} from '@/shared/api/eventHistoryApi';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));

const loading = ref(false);
const items = ref<EventHistoryItem[]>([]);

const myPermissionSlugs = ref<string[]>([]);

const deleteConfirmOpen = ref(false);
const deleteTarget = ref<EventHistoryItem | null>(null);
const deleteLoading = ref(false);

const canCreate = computed(() => myPermissionSlugs.value.includes('dobavliat-sobytie'));
const canEdit = computed(() => myPermissionSlugs.value.includes('redaktirovat-sobytie'));
const canDelete = computed(() => myPermissionSlugs.value.includes('udaliat-sobytie'));

function goToShow(item: EventHistoryItem) {
  if (!guildId.value) return;
  router.push({
    name: 'guild-events-show',
    params: { id: guildId.value, eventHistoryId: item.id },
  });
}

async function fetchPermissionsAndRoster() {
  if (!guildId.value) return;
  try {
    const guild = await guildsApi.getGuildForSettings(guildId.value);
    myPermissionSlugs.value = guild.my_permission_slugs ?? [];
  } catch {
    myPermissionSlugs.value = [];
  }

}

async function fetchHistory() {
  if (!guildId.value) return;
  loading.value = true;
  try {
    items.value = await eventHistoryApi.list(guildId.value);
  } catch {
    items.value = [];
  } finally {
    loading.value = false;
  }
}

function goToCreate() {
  if (!guildId.value) return;
  router.push({ name: 'guild-events-create', params: { id: guildId.value } });
}

function goToEdit(item: EventHistoryItem) {
  if (!guildId.value) return;
  router.push({
    name: 'guild-events-edit',
    params: { id: guildId.value, eventHistoryId: item.id },
  });
}

function askDelete(item: EventHistoryItem) {
  deleteTarget.value = item;
  deleteConfirmOpen.value = true;
}

async function confirmDelete() {
  if (!guildId.value || !deleteTarget.value) return;
  deleteLoading.value = true;
  try {
    await eventHistoryApi.delete(guildId.value, deleteTarget.value.id);
    deleteConfirmOpen.value = false;
    deleteTarget.value = null;
    await fetchHistory();
  } catch {
    // ошибка уже обработана через throwOnError
  } finally {
    deleteLoading.value = false;
  }
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

async function loadEventsPage() {
  items.value = [];
  myPermissionSlugs.value = [];
  deleteConfirmOpen.value = false;
  deleteTarget.value = null;
  deleteLoading.value = false;
  loading.value = false;

  await fetchPermissionsAndRoster();
  await fetchHistory();
}

watch(guildId, () => {
  loadEventsPage();
}, { immediate: true });
</script>

<template>
  <div class="container py-6 space-y-4 max-w-2xl mx-auto">
    <div class="flex items-center justify-between gap-2">
      <h1 class="text-xl font-semibold">
        События гильдии
      </h1>
      <Button
        v-if="canCreate"
        size="sm"
        @click="goToCreate"
      >
        Добавить событие
      </Button>
    </div>

    <div>
      <p
        v-if="loading"
        class="text-sm text-muted-foreground"
      >
        Загрузка истории событий...
      </p>
      <p
        v-else-if="!items.length"
        class="text-sm text-muted-foreground"
      >
        Пока нет записей в истории событий.
      </p>
      <ul
        v-else
        class="space-y-4"
      >
        <li
          v-for="item in items"
          :key="item.id"
          class="rounded-md border p-3 flex flex-col gap-2"
        >
          <div class="flex items-center justify-between gap-3">
            <button
              type="button"
              class="min-w-0 flex-1 text-left"
              @click="goToShow(item)"
            >
              <div class="flex flex-col gap-0.5">
                <span class="font-semibold truncate">
                  {{ item.title }}
                </span>
                <span
                  v-if="item.occurred_at"
                  class="text-xs text-muted-foreground"
                >
                  {{ formatDateTime(item.occurred_at) }}
                </span>
              </div>
            </button>
            <div class="flex items-center gap-1">
              <Button
                v-if="canEdit"
                size="icon"
                variant="ghost"
                class="h-8 w-8 text-muted-foreground hover:text-foreground"
                aria-label="Редактировать"
                @click.stop="goToEdit(item)"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z" />
                </svg>
              </Button>
              <Button
                v-if="canDelete"
                size="icon"
                variant="ghost"
                class="h-8 w-8 text-muted-foreground hover:text-destructive"
                aria-label="Удалить"
                @click.stop="askDelete(item)"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M3 6h18" />
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                </svg>
              </Button>
            </div>
          </div>
        </li>
      </ul>
    </div>


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

