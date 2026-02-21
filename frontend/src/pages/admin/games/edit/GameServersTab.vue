<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { gamesApi, type Game, type Localization, type Server } from '@/shared/api/gamesApi';
import { ref } from 'vue';

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

function startAddServer(loc: Localization) {
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
  if (!props.game || !newServerName.value.trim() || !newServerSlug.value.trim()) return;
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
    await gamesApi.mergeServers(props.game.id, loc.id, {
      target_server_id: mergeTargetId.value,
      source_server_ids: mergeSourceIds.value,
    });
    const updated = await gamesApi.getGame(props.game.id);
    emit('update:game', updated);
    cancelMerge();
  } catch (e: unknown) {
    mergeError.value = e instanceof Error ? e.message : 'Ошибка объединения';
  } finally {
    mergeSubmitting.value = false;
  }
}
</script>

<template>
  <Card v-if="game.localizations?.length">
    <CardHeader>
      <CardTitle>Сервера по локализациям</CardTitle>
      <p class="text-sm text-muted-foreground">
        Управление серверами для каждой локализации. Можно объединять сервера (персонажи и гильдии переносятся на целевой).
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
              <button
                type="button"
                class="ml-1 rounded p-0.5 text-destructive hover:bg-destructive/10"
                :disabled="deletingServerId === srv.id"
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
          <Button v-else type="button" size="sm" variant="outline" @click="startAddServer(loc)">
            Добавить сервер
          </Button>
        </div>

        <div v-if="(loc.servers?.length ?? 0) >= 2">
          <h5 class="mb-2 text-sm font-medium text-muted-foreground">Объединить сервера</h5>
          <p class="mb-2 text-xs text-muted-foreground">
            Персонажи и гильдии с выбранных серверов переедут на целевой сервер; объединённые сервера будут отключены.
          </p>
          <div v-if="mergeForLocId === loc.id" class="space-y-3 rounded border p-3">
            <div>
              <Label class="text-xs">Целевой сервер (на него переносятся данные)</Label>
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
                    class="rounded-full border-input"
                    @change="onMergeTargetChange(loc)"
                  />
                  {{ srv.name }}
                </label>
              </div>
            </div>
            <div>
              <Label class="text-xs">Объединяемые сервера (будут отключены)</Label>
              <div class="mt-1 flex flex-wrap gap-3">
                <label
                  v-for="srv in loc.servers"
                  :key="srv.id"
                  class="flex items-center gap-1.5 text-sm"
                >
                  <input
                    type="checkbox"
                    :checked="mergeSourceIds.includes(srv.id)"
                    :disabled="mergeTargetId === srv.id"
                    class="rounded border-input"
                    @change="toggleMergeSource(srv.id)"
                  />
                  <span :class="{ 'text-muted-foreground': mergeTargetId === srv.id }">{{ srv.name }}</span>
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
                {{ mergeSubmitting ? '...' : 'Объединить' }}
              </Button>
              <Button type="button" size="sm" variant="ghost" @click="cancelMerge">Отмена</Button>
            </div>
            <p v-if="mergeError" class="text-sm text-destructive">{{ mergeError }}</p>
          </div>
          <Button v-else type="button" size="sm" variant="outline" @click="startMerge(loc)">
            Объединить сервера
          </Button>
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
