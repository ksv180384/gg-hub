<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

const authStore = useAuthStore();
const siteContext = useSiteContextStore();
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

const canSubmit = computed(() => name.value.trim().length > 0 && slug.value.trim().length > 0);

/** Превью: новый файл, либо текущее изображение (если не удаляем) */
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
    router.replace('/games');
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
    if (err.status === 404) router.replace('/games');
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
    await router.push('/games');
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> };
    if (err.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(err.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)])
      );
    }
    error.value = err.message ?? 'Не удалось сохранить изменения';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  if (!siteContext.isAdmin) {
    router.replace('/games');
    return;
  }
  if (!authStore.isAuthenticated) {
    router.replace('/login');
    return;
  }
  loadGame();
});

watch(gameId, (id) => {
  if (id && !Number.isNaN(id)) loadGame();
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Редактировать игру</h1>
      <p class="mb-8 text-muted-foreground">
        Измените данные игры. Можно заменить или удалить изображение.
      </p>

      <div v-if="loading" class="text-muted-foreground">Загрузка...</div>

      <Card v-else-if="game">
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
              <Button type="button" variant="outline" @click="router.push('/games')">
                Отмена
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <div v-else class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Игра не найдена.
      </div>
    </div>
  </div>
</template>
