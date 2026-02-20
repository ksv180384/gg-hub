<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label, Badge } from '@/shared/ui';
import { gamesApi, type Game, type Localization, type Server } from '@/shared/api/gamesApi';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';

function slugFromName(s: string): string {
  return s
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

const router = useRouter();
const route = useRoute();
const gameId = computed(() => Number(route.params.id));

const game = ref<Game | null>(null);
const loading = ref(true);
const name = ref('');
const slug = ref('');
const description = ref('');
const imageFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const removeImage = ref(false);
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const submitting = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});

const locCode = ref('');
const locName = ref('');
const locSubmitting = ref(false);
const locError = ref<string | null>(null);

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

const canSubmit = computed(() => name.value.trim().length > 0 && slug.value.trim().length > 0);

const displayImageUrl = computed(() => {
  if (imagePreview.value) return imagePreview.value;
  if (removeImage.value || !game.value?.image) return null;
  return storageImageUrl(game.value.image);
});

function setImageFile(file: File | null) {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
  imageFile.value = file ?? null;
  imagePreview.value = file ? URL.createObjectURL(file) : null;
  if (file) removeImage.value = false;
}

function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file?.type.startsWith('image/')) setImageFile(file);
  target.value = '';
}

function onDrop(e: DragEvent) {
  dragOver.value = false;
  const file = e.dataTransfer?.files?.[0];
  if (file?.type.startsWith('image/')) setImageFile(file);
  e.preventDefault();
}

function onDragOver(e: DragEvent) {
  dragOver.value = true;
  e.preventDefault();
  e.dataTransfer!.dropEffect = 'copy';
}

function onDragLeave() {
  dragOver.value = false;
}

function openFilePicker() {
  fileInputRef.value?.click();
}

function clearNewImage() {
  setImageFile(null);
}

function markRemoveImage() {
  removeImage.value = true;
  setImageFile(null);
}

async function loadGame() {
  const id = gameId.value;
  if (!id || Number.isNaN(id)) {
    router.replace('/admin/games');
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    game.value = await gamesApi.getGame(id);
    name.value = game.value.name;
    slug.value = game.value.slug;
    description.value = game.value.description ?? '';
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) router.replace('/admin/games');
    else error.value = err.message ?? 'Не удалось загрузить игру';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!canSubmit.value || !game.value) return;
  submitting.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    await gamesApi.updateGame(game.value.id, {
      name: name.value.trim(),
      slug: slug.value.trim(),
      description: description.value.trim() || undefined,
      image: imageFile.value || undefined,
      remove_image: removeImage.value,
    });
    await router.push('/admin/games');
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> };
    if (err.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(err.errors).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : String(v)])
      ) as Record<string, string>;
    }
    error.value = err.message ?? 'Не удалось сохранить изменения';
  } finally {
    submitting.value = false;
  }
}

async function submitLocalization() {
  if (!game.value) return;
  locSubmitting.value = true;
  locError.value = null;
  try {
    await gamesApi.createLocalization(game.value.id, {
      code: locCode.value.trim(),
      name: locName.value.trim(),
    });
    locCode.value = '';
    locName.value = '';
    const updated = await gamesApi.getGame(game.value.id);
    game.value = updated;
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> };
    locError.value =
      err.errors?.code?.[0] ?? err.errors?.name?.[0] ?? err.message ?? 'Ошибка добавления локализации';
  } finally {
    locSubmitting.value = false;
  }
}

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
  if (!game.value || !newServerName.value.trim() || !newServerSlug.value.trim()) return;
  serverSubmitting.value = true;
  serverError.value = null;
  try {
    await gamesApi.createServer(game.value.id, loc.id, {
      name: newServerName.value.trim(),
      slug: newServerSlug.value.trim(),
    });
    const updated = await gamesApi.getGame(game.value.id);
    game.value = updated;
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
  if (!game.value || deletingServerId.value !== null) return;
  deletingServerId.value = server.id;
  try {
    await gamesApi.deleteServer(server.id);
    const updated = await gamesApi.getGame(game.value.id);
    game.value = updated;
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
  if (!game.value || mergeSourceIds.value.length === 0) return;
  mergeSubmitting.value = true;
  mergeError.value = null;
  try {
    await gamesApi.mergeServers(game.value.id, loc.id, {
      target_server_id: mergeTargetId.value,
      source_server_ids: mergeSourceIds.value,
    });
    const updated = await gamesApi.getGame(game.value.id);
    game.value = updated;
    cancelMerge();
  } catch (e: unknown) {
    mergeError.value = e instanceof Error ? e.message : 'Ошибка объединения';
  } finally {
    mergeSubmitting.value = false;
  }
}

onMounted(() => loadGame());

watch(gameId, (id) => {
  if (id && !Number.isNaN(id)) loadGame();
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Редактировать игру</h1>
      <p class="mb-8 text-muted-foreground">
        Измените данные игры и локализации.
      </p>

      <div v-if="loading" class="text-muted-foreground">Загрузка...</div>

      <template v-else-if="game">
        <Card class="mb-8">
          <CardHeader>
            <CardTitle>{{ game.name }}</CardTitle>
          </CardHeader>
          <CardContent>
            <form class="space-y-6" @submit.prevent="submit">
              <div v-if="error" class="rounded-md bg-destructive/10 p-4 text-sm text-destructive">
                {{ error }}
              </div>

              <div class="space-y-2">
                <Label for="name">Название *</Label>
                <Input
                  id="name"
                  v-model="name"
                  placeholder="World of Warcraft"
                  required
                />
                <p v-if="fieldErrors.name" class="text-sm text-destructive">{{ fieldErrors.name }}</p>
              </div>

              <div class="space-y-2">
                <Label for="slug">Slug *</Label>
                <Input
                  id="slug"
                  v-model="slug"
                  placeholder="world-of-warcraft"
                  required
                />
                <p class="text-xs text-muted-foreground">URL-идентификатор игры.</p>
                <p v-if="fieldErrors.slug" class="text-sm text-destructive">{{ fieldErrors.slug }}</p>
              </div>

              <div class="space-y-2">
                <Label for="description">Описание</Label>
                <textarea
                  id="description"
                  v-model="description"
                  placeholder="Краткое описание игры"
                  rows="4"
                  class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
                <p v-if="fieldErrors.description" class="text-sm text-destructive">{{ fieldErrors.description }}</p>
              </div>

              <div class="space-y-2">
                <Label>Изображение</Label>
                <input
                  ref="fileInputRef"
                  type="file"
                  :accept="ACCEPT_IMAGES"
                  class="sr-only"
                  @change="onFileChange"
                />
                <div
                  class="relative min-h-[180px] rounded-lg border-2 border-dashed transition-colors"
                  :class="dragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 hover:border-muted-foreground/50'"
                  @drop="onDrop"
                  @dragover="onDragOver"
                  @dragleave="onDragLeave"
                >
                  <div
                    v-if="!displayImageUrl"
                    class="flex h-[180px] flex-col items-center justify-center gap-2 text-muted-foreground"
                  >
                    <div
                      class="flex flex-col items-center gap-2"
                      role="button"
                      tabindex="0"
                      @click="openFilePicker"
                      @keydown.enter="openFilePicker"
                      @keydown.space.prevent="openFilePicker"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="opacity-60">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                        <circle cx="9" cy="9" r="2"/>
                        <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                      </svg>
                      <span class="text-sm">Перетащите изображение сюда или нажмите для выбора</span>
                    </div>
                    <button
                      v-if="removeImage && game?.image"
                      type="button"
                      class="text-sm text-primary underline hover:no-underline"
                      @click.stop="removeImage = false"
                    >
                      Восстановить текущее изображение
                    </button>
                  </div>
                  <div v-else class="p-3">
                    <div class="relative inline-block">
                      <img
                        :src="displayImageUrl"
                        alt="Превью"
                        class="max-h-[200px] rounded-lg border object-contain shadow-sm"
                      />
                      <div class="mt-2 flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" @click="openFilePicker">
                          Заменить
                        </Button>
                        <Button
                          v-if="game.image || imageFile"
                          type="button"
                          variant="outline"
                          size="sm"
                          class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                          @click="markRemoveImage"
                        >
                          Удалить изображение
                        </Button>
                        <Button
                          v-if="imageFile"
                          type="button"
                          variant="ghost"
                          size="sm"
                          @click="clearNewImage"
                        >
                          Отменить новое
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
                <p v-if="fieldErrors.image" class="text-sm text-destructive">{{ fieldErrors.image }}</p>
              </div>

              <div class="flex gap-2">
                <Button type="submit" :disabled="!canSubmit || submitting">
                  {{ submitting ? 'Сохранение...' : 'Сохранить' }}
                </Button>
                <Button type="button" variant="outline" @click="router.push('/admin/games')">
                  Отмена
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Локализации</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-if="game.localizations?.length" class="flex flex-wrap gap-2">
              <Badge v-for="loc in game.localizations" :key="loc.id" variant="secondary">
                {{ loc.code }}: {{ loc.name }}
              </Badge>
            </div>
            <p v-else class="text-sm text-muted-foreground">Нет локализаций.</p>
            <form class="flex flex-wrap items-end gap-3 border-t pt-4" @submit.prevent="submitLocalization">
              <div class="space-y-1">
                <Label for="edit-loc-code" class="text-xs">Код</Label>
                <Input
                  id="edit-loc-code"
                  v-model="locCode"
                  placeholder="ru"
                  maxlength="16"
                  class="w-24"
                />
              </div>
              <div class="space-y-1">
                <Label for="edit-loc-name" class="text-xs">Название</Label>
                <Input
                  id="edit-loc-name"
                  v-model="locName"
                  placeholder="Русский"
                  class="w-40"
                />
              </div>
              <Button type="submit" size="sm" :disabled="locSubmitting || !locCode.trim() || !locName.trim()">
                {{ locSubmitting ? 'Сохранение...' : 'Добавить' }}
              </Button>
              <p v-if="locError" class="w-full text-sm text-destructive">{{ locError }}</p>
            </form>
          </CardContent>
        </Card>

        <Card v-if="game.localizations?.length" class="mt-8">
          <CardHeader>
            <CardTitle>Сервера по локализациям</CardTitle>
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
      </template>

      <div v-else class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Игра не найдена.
      </div>
    </div>
  </div>
</template>
