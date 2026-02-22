<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';

const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

const props = defineProps<{ game: Game }>();
const emit = defineEmits<{ (e: 'update:game', game: Game): void }>();

const router = useRouter();
const name = ref('');
const slug = ref('');
const description = ref('');
const maxClassesPerCharacter = ref('1');
const imageFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const removeImage = ref(false);
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const submitting = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});

const canSubmit = computed(() => name.value.trim().length > 0 && slug.value.trim().length > 0);

const displayImageUrl = computed(() => {
  if (imagePreview.value) return imagePreview.value;
  if (removeImage.value || !props.game?.image) return null;
  return storageImageUrl(props.game.image);
});

watch(
  () => props.game,
  (g) => {
    if (g) {
      name.value = g.name;
      slug.value = g.slug;
      description.value = g.description ?? '';
      maxClassesPerCharacter.value = String(g.max_classes_per_character ?? 1);
    }
  },
  { immediate: true }
);

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

function markRemoveImage() {
  removeImage.value = true;
  setImageFile(null);
}

function clearNewImage() {
  setImageFile(null);
}

async function submit() {
  if (!canSubmit.value || !props.game) return;
  submitting.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    const updated = await gamesApi.updateGame(props.game.id, {
      name: name.value.trim(),
      slug: slug.value.trim(),
      description: description.value.trim() || undefined,
      image: imageFile.value || undefined,
      remove_image: removeImage.value,
      max_classes_per_character: parseInt(maxClassesPerCharacter.value, 10) || 1,
    });
    emit('update:game', updated);
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
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Игра</CardTitle>
      <p class="text-sm text-muted-foreground">Основные данные игры.</p>
    </CardHeader>
    <CardContent>
      <form class="space-y-6" @submit.prevent="submit">
        <div v-if="error" class="rounded-md bg-destructive/10 p-4 text-sm text-destructive">
          {{ error }}
        </div>

        <div class="space-y-2">
          <Label for="name">Название *</Label>
          <Input id="name" v-model="name" placeholder="World of Warcraft" required />
          <p v-if="fieldErrors.name" class="text-sm text-destructive">{{ fieldErrors.name }}</p>
        </div>

        <div class="space-y-2">
          <Label for="slug">Slug *</Label>
          <Input id="slug" v-model="slug" placeholder="world-of-warcraft" required />
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
          <Label for="max-classes">Классов у персонажа (макс.)</Label>
          <Input
            id="max-classes"
            v-model="maxClassesPerCharacter"
            type="number"
            min="0"
            max="255"
            class="w-24"
          />
          <p class="text-xs text-muted-foreground">Сколько классов можно присвоить одному персонажу в этой игре.</p>
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
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="opacity-60">
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
                <img :src="displayImageUrl" alt="Превью" class="max-h-[200px] rounded-lg border object-contain shadow-sm" />
                <div class="mt-2 flex flex-wrap gap-2">
                  <Button type="button" variant="outline" size="sm" @click="openFilePicker">Заменить</Button>
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
          <Button type="button" variant="outline" @click="router.push('/admin/games')">Отмена</Button>
        </div>
      </form>
    </CardContent>
  </Card>
</template>
