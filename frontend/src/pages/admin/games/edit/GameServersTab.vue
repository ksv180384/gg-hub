<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import {
  gamesApi,
  type Game,
  type Localization,
  type Server,
  type ServerMerge,
} from '@/shared/api/gamesApi';
import { onBeforeUnmount, onMounted, ref } from 'vue';

function slugFromName(s: string): string {
  return s
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

const props = defineProps<{ game: Game }>();
const emit = defineEmits<{ (e: 'update:game', game: Game): void }>();

const addServerForLocId = ref<number | null>(null);
const newServerName = ref('');
const newServerSlug = ref('');
const serverSubmitting = ref(false);
const serverError = ref<string | null>(null);
const deletingServerId = ref<number | null>(null);
const mergeForLocId = ref<number | null>(null);
const mergeTargetId = ref<number>(0);
const mergeSourceIds = ref<number[]>([]);
const mergeSubmitting = ref(false);
const mergeError = ref<string | null>(null);
const mergeStatuses = ref<Record<number, ServerMerge | null>>({});
const mergeResumingId = ref<number | null>(null);
let mergePollingTimer: ReturnType<typeof setInterval> | null = null;
let mergePolling = false;

function startAddServer(loc: Localization) {
  if (mergeBlocksChanges(loc.id)) return;
  addServerForLocId.value = loc.id;
  newServerName.value = '';
  newServerSlug.value = '';
  serverError.value = null;
}

function cancelAddServer() {
  addServerForLocId.value = null;
  serverError.value = null;
}

async function submitServer(loc: Localization) {
  if (!props.game || mergeBlocksChanges(loc.id) || !newServerName.value.trim() || !newServerSlug.value.trim()) return;
  serverSubmitting.value = true;
  serverError.value = null;
  try {
    await gamesApi.createServer(props.game.id, loc.id, {
      name: newServerName.value.trim(),
      slug: newServerSlug.value.trim(),
    });
    const updated = await gamesApi.getGame(props.game.id);
    emit('update:game', updated);
    cancelAddServer();
  } catch (e: unknown) {
    const msg =
      e instanceof Error
        ? (e as Error & { errors?: Record<string, string[]> }).message
        : typeof e === 'object' && e != null && 'message' in e && typeof (e as { message: unknown }).message === 'string'
          ? (e as { message: string }).message
          : 'Ошибка добавления сервера';
    serverError.value = msg || 'Ошибка добавления сервера';
  } finally {
    serverSubmitting.value = false;
  }
}

async function deleteServer(server: Server) {
  if (!props.game || deletingServerId.value !== null) return;
  deletingServerId.value = server.id;
  try {
    await gamesApi.deleteServer(server.id);
    const updated = await gamesApi.getGame(props.game.id);
    emit('update:game', updated);
  } catch {
    // ignore
  } finally {
    deletingServerId.value = null;
  }
}

const mergeStageLabels: Record<string, string> = {
  characters: 'Персонажи',
  guilds: 'Гильдии',
  constant_parties: 'Конст пати',
  server_groups: 'Группы серверов',
  finalizing: 'Завершение',
};

function mergeStatusFor(localizationId: number): ServerMerge | null {
  return mergeStatuses.value[localizationId] ?? null;
}

function mergeBlocksChanges(localizationId: number): boolean {
  const status = mergeStatusFor(localizationId)?.status;
  return status === 'pending' || status === 'running' || status === 'failed';
}

function mergeStatusLabel(status: ServerMerge['status'] | undefined): string {
  if (!status) return '';

  return {
    pending: 'Ожидает запуска',
    running: 'Выполняется',
    completed: 'Завершено',
    failed: 'Ошибка',
  }[status];
}

function mergeProgressStyle(localizationId: number): Record<string, string> {
  return { width: String(mergeStatusFor(localizationId)?.progress_percent ?? 0) + '%' };
}

function setMergeStatus(localizationId: number, merge: ServerMerge | null) {
  mergeStatuses.value = {
    ...mergeStatuses.value,
    [localizationId]: merge,
  };
}

async function refreshGame() {
  const updated = await gamesApi.getGame(props.game.id);
  emit('update:game', updated);
}

function stopMergePolling() {
  if (mergePollingTimer !== null) {
    clearInterval(mergePollingTimer);
    mergePollingTimer = null;
  }
}

function ensureMergePolling() {
  if (mergePollingTimer === null) {
    mergePollingTimer = setInterval(() => void pollServerMerges(), 2000);
  }
}

async function pollServerMerges() {
  if (mergePolling) return;
  mergePolling = true;
  let shouldRefreshGame = false;

  try {
    for (const localization of props.game.localizations ?? []) {
      const current = mergeStatusFor(localization.id);
      if (!current || (current.status !== 'pending' && current.status !== 'running')) {
        continue;
      }

      const next = await gamesApi.getServerMerge(current.id);
      setMergeStatus(localization.id, next);
      if (next.status === 'completed') {
        shouldRefreshGame = true;
      }
    }

    if (shouldRefreshGame) {
      await refreshGame();
    }

    if (!Object.values(mergeStatuses.value).some((merge) =>
      merge?.status === 'pending' || merge?.status === 'running'
    )) {
      stopMergePolling();
    }
  } catch (error: unknown) {
    mergeError.value = error instanceof Error ? error.message : 'Ошибка загрузки статуса объединения';
  } finally {
    mergePolling = false;
  }
}

async function loadMergeStatuses() {
  await Promise.all(
    (props.game.localizations ?? []).map(async (localization) => {
      const merge = await gamesApi.getCurrentServerMerge(props.game.id, localization.id);
      setMergeStatus(localization.id, merge);
    })
  );

  if (Object.values(mergeStatuses.value).some((merge) =>
    merge?.status === 'pending' || merge?.status === 'running'
  )) {
    ensureMergePolling();
    await pollServerMerges();
  }
}

onMounted(() => void loadMergeStatuses());
onBeforeUnmount(stopMergePolling);

function startMerge(loc: Localization) {
  mergeForLocId.value = loc.id;
  const servers = loc.servers ?? [];
  mergeTargetId.value = servers[0]?.id ?? 0;
  mergeSourceIds.value = servers.filter((s) => s.id !== mergeTargetId.value).map((s) => s.id);
  mergeError.value = null;
}

function onMergeTargetChange(loc: Localization) {
  mergeSourceIds.value = (loc.servers ?? []).filter((s) => s.id !== mergeTargetId.value).map((s) => s.id);
}

function cancelMerge() {
  mergeForLocId.value = null;
  mergeError.value = null;
}

function toggleMergeSource(serverId: number) {
  const idx = mergeSourceIds.value.indexOf(serverId);
  if (idx === -1) mergeSourceIds.value = [...mergeSourceIds.value, serverId];
  else mergeSourceIds.value = mergeSourceIds.value.filter((id) => id !== serverId);
}

async function submitMerge(loc: Localization) {
  if (!props.game || mergeSourceIds.value.length === 0) return;
  mergeSubmitting.value = true;
  mergeError.value = null;
  try {
    const merge = await gamesApi.mergeServers(props.game.id, loc.id, {
      target_server_id: mergeTargetId.value,
      source_server_ids: mergeSourceIds.value,
    });
    setMergeStatus(loc.id, merge);
    cancelMerge();

    if (merge.status === 'completed') {
      await refreshGame();
    } else {
      ensureMergePolling();
      await pollServerMerges();
    }
  } catch (error: unknown) {
    mergeError.value = error instanceof Error ? error.message : 'Ошибка объединения';
  } finally {
    mergeSubmitting.value = false;
  }
}

async function resumeMerge(loc: Localization) {
  const current = mergeStatusFor(loc.id);
  if (!current) return;

  mergeResumingId.value = current.id;
  mergeError.value = null;
  try {
    const merge = await gamesApi.resumeServerMerge(current.id);
    setMergeStatus(loc.id, merge);
    ensureMergePolling();
    await pollServerMerges();
  } catch (error: unknown) {
    mergeError.value = error instanceof Error ? error.message : 'Ошибка продолжения объединения';
  } finally {
    mergeResumingId.value = null;
  }
}
</script>

<template>
  <Card v-if="game.localizations?.length">
    <CardHeader>
      <CardTitle>Сервера по локализациям</CardTitle>
      <p class="text-sm text-muted-foreground">
        Управление серверами для каждой локализации. Объединение выполняется в фоне с сохранением прогресса.
      </p>
    </CardHeader>
    <CardContent class="space-y-8">
      <div
        v-for="loc in game.localizations"
        :key="loc.id"
        class="rounded-lg border bg-muted/30 p-4 space-y-4"
      >
        <h4 class="font-medium">{{ loc.code }}: {{ loc.name }}</h4>

        <div>
          <h5 class="mb-2 text-sm font-medium text-muted-foreground">Сервера</h5>
          <ul v-if="loc.servers?.length" class="mb-2 flex flex-wrap gap-2">
            <li
              v-for="srv in loc.servers"
              :key="srv.id"
              class="flex items-center gap-1 rounded-md bg-background px-2 py-1 text-sm"
            >
              <span>{{ srv.name }}</span>
              <span class="text-muted-foreground">({{ srv.slug }})</span>
              <span v-if="srv.is_merging" class="text-xs text-amber-700">объединяется</span>
              <button
                type="button"
                class="ml-1 rounded p-0.5 text-destructive hover:bg-destructive/10"
                :disabled="deletingServerId === srv.id || srv.is_merging || mergeBlocksChanges(loc.id)"
                aria-label="Удалить сервер"
                @click="deleteServer(srv)"
              >
                ×
              </button>
            </li>
          </ul>
          <p v-else class="mb-2 text-sm text-muted-foreground">Нет серверов.</p>
          <div v-if="addServerForLocId === loc.id" class="flex flex-wrap items-end gap-2 rounded border p-2">
            <div class="space-y-1">
              <Label class="text-xs">Название</Label>
              <Input v-model="newServerName" placeholder="Сервер 1" class="w-32" @input="newServerSlug = slugFromName(newServerName) || newServerSlug" />
            </div>
            <div class="space-y-1">
              <Label class="text-xs">Slug</Label>
              <Input v-model="newServerSlug" placeholder="server-1" class="w-28" />
            </div>
            <Button type="button" size="sm" :disabled="serverSubmitting || !newServerName.trim() || !newServerSlug.trim()" @click="submitServer(loc)">
              {{ serverSubmitting ? '...' : 'Добавить' }}
            </Button>
            <Button type="button" size="sm" variant="ghost" @click="cancelAddServer">Отмена</Button>
            <p v-if="serverError" class="w-full text-sm text-destructive">{{ serverError }}</p>
          </div>
          <Button v-else type="button" size="sm" variant="outline" :disabled="mergeBlocksChanges(loc.id)" @click="startAddServer(loc)">
            Добавить сервер
          </Button>
        </div>

        <div v-if="(loc.servers?.length ?? 0) >= 2 || mergeStatusFor(loc.id)" class="space-y-3">
          <h5 class="text-sm font-medium text-muted-foreground">Объединить сервера</h5>
          <p class="text-xs text-muted-foreground">
            Персонажи, гильдии, конст пати и группы серверов переносятся в фоне небольшими пачками.
          </p>

          <div
            v-if="mergeStatusFor(loc.id)"
            class="space-y-2 rounded border bg-background p-3"
          >
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
              <span class="font-medium">
                {{ mergeStatusLabel(mergeStatusFor(loc.id)?.status) }}
              </span>
              <span class="tabular-nums text-muted-foreground">
                {{ mergeStatusFor(loc.id)?.processed_records }} /
                {{ mergeStatusFor(loc.id)?.total_records }}
              </span>
            </div>
            <div class="h-2 overflow-hidden rounded bg-muted">
              <div
                class="h-full bg-primary transition-[width] duration-300"
                :style="mergeProgressStyle(loc.id)"
              />
            </div>
            <div class="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
              <span>Прогресс: {{ mergeStatusFor(loc.id)?.progress_percent }}%</span>
              <span v-if="mergeStatusFor(loc.id)?.current_stage">
                Этап:
                {{ mergeStageLabels[mergeStatusFor(loc.id)?.current_stage ?? ''] ?? mergeStatusFor(loc.id)?.current_stage }}
              </span>
            </div>
            <p
              v-if="mergeStatusFor(loc.id)?.error_message"
              class="text-sm text-destructive"
            >
              {{ mergeStatusFor(loc.id)?.error_message }}
            </p>
            <Button
              v-if="mergeStatusFor(loc.id)?.can_resume"
              type="button"
              size="sm"
              :disabled="mergeResumingId === mergeStatusFor(loc.id)?.id"
              @click="resumeMerge(loc)"
            >
              {{ mergeResumingId === mergeStatusFor(loc.id)?.id ? '...' : 'Продолжить' }}
            </Button>
          </div>

          <div
            v-if="mergeForLocId === loc.id && !mergeBlocksChanges(loc.id)"
            class="space-y-3 rounded border p-3"
          >
            <div>
              <Label class="text-xs">Целевой сервер</Label>
              <div class="mt-1 flex flex-wrap gap-3">
                <label
                  v-for="srv in loc.servers"
                  :key="srv.id"
                  class="flex items-center gap-1.5 text-sm"
                >
                  <input
                    v-model="mergeTargetId"
                    type="radio"
                    :value="srv.id"
                    :disabled="srv.is_merging"
                    class="rounded-full border-input"
                    @change="onMergeTargetChange(loc)"
                  />
                  {{ srv.name }}
                </label>
              </div>
            </div>
            <div>
              <Label class="text-xs">Объединяемые сервера</Label>
              <div class="mt-1 flex flex-wrap gap-3">
                <label
                  v-for="srv in loc.servers"
                  :key="srv.id"
                  class="flex items-center gap-1.5 text-sm"
                >
                  <input
                    type="checkbox"
                    :checked="mergeSourceIds.includes(srv.id)"
                    :disabled="mergeTargetId === srv.id || srv.is_merging"
                    class="rounded border-input"
                    @change="toggleMergeSource(srv.id)"
                  />
                  <span :class="{ 'text-muted-foreground': mergeTargetId === srv.id }">
                    {{ srv.name }}
                  </span>
                </label>
              </div>
            </div>
            <div class="flex gap-2">
              <Button
                type="button"
                size="sm"
                :disabled="mergeSubmitting || mergeSourceIds.length === 0"
                @click="submitMerge(loc)"
              >
                {{ mergeSubmitting ? '...' : 'Запустить объединение' }}
              </Button>
              <Button type="button" size="sm" variant="ghost" @click="cancelMerge">
                Отмена
              </Button>
            </div>
          </div>
          <Button
            v-else-if="(loc.servers?.length ?? 0) >= 2 && !mergeBlocksChanges(loc.id)"
            type="button"
            size="sm"
            variant="outline"
            @click="startMerge(loc)"
          >
            Объединить сервера
          </Button>
          <p v-if="mergeError" class="text-sm text-destructive">{{ mergeError }}</p>
        </div>
      </div>
    </CardContent>
  </Card>
  <Card v-else>
    <CardContent class="py-8 text-center text-muted-foreground">
      Сначала добавьте локализации на вкладке «Локализации».
    </CardContent>
  </Card>
</template>
