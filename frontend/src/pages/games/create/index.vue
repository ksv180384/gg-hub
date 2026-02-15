<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { gamesApi } from '@/shared/api/gamesApi';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

function slugFromName(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

const authStore = useAuthStore();
const siteContext = useSiteContextStore();
const router = useRouter();
const name = ref('');
const slug = ref('');
const description = ref('');
const imageFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const submitting = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});

const suggestedSlug = computed(() => slugFromName(name.value));
const effectiveSlug = computed(() => (slug.value.trim() || suggestedSlug.value));
const canSubmit = computed(() => name.value.trim().length > 0 && effectiveSlug.value.length > 0);

function setImageFile(file: File | null) {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
  imageFile.value = file ?? null;
  imagePreview.value = file ? URL.createObjectURL(file) : null;
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

function removeImage() {
  setImageFile(null);
}

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    await gamesApi.createGame({
      name: name.value.trim(),
      slug: effectiveSlug.value,
      description: description.value.trim() || undefined,
      image: imageFile.value,
    });
    await router.push('/games');
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> };
    if (err.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(err.errors).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : String(v)])
      ) as Record<string, string>;
    }
    error.value = err.message ?? 'Не удалось создать игру';
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
  }
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Добавить игру</h1>
      <p class="mb-8 text-muted-foreground">
        Заполните форму. Изображение и описание необязательны. Доступно только авторизованным пользователям.
      </p>

      <Card>
        <CardHeader>
          <CardTitle>Новая игра</CardTitle>
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
                placeholder="Подставлено из названия"
                :title="suggestedSlug ? `Сейчас подставится: ${suggestedSlug}` : ''"
              />
              <p class="text-xs text-muted-foreground">
                URL-идентификатор. Если пусто — подставится из названия.
              </p>
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
                  v-if="!imagePreview"
                  class="flex h-[180px] flex-col items-center justify-center gap-2 text-muted-foreground"
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
                <div v-else class="p-3">
                  <div class="relative inline-block">
                    <img
                      :src="imagePreview"
                      alt="Превью"
                      class="max-h-[200px] rounded-lg border object-contain shadow-sm"
                    />
                    <button
                      type="button"
                      class="absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-destructive text-destructive-foreground shadow hover:bg-destructive/90"
                      aria-label="Удалить изображение"
                      @click.stop="removeImage"
                    >
                      <span class="text-sm leading-none">×</span>
                    </button>
                  </div>
                </div>
              </div>
              <p v-if="fieldErrors.image" class="text-sm text-destructive">{{ fieldErrors.image }}</p>
            </div>

            <div class="flex gap-2">
              <Button type="submit" :disabled="!canSubmit || submitting">
                {{ submitting ? 'Сохранение...' : 'Создать игру' }}
              </Button>
              <Button type="button" variant="outline" @click="router.push('/games')">
                Отмена
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
