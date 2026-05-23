<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Card, CardHeader, CardTitle, CardContent, Button, Input } from '@/shared/ui';
import { cn } from '@/shared/lib/utils';

type ScreenshotRow = {
  url?: string;
  title: string;
  file?: File;
  previewUrl?: string;
};

const MAX_SCREENSHOTS = 5;

const screenshots = defineModel<ScreenshotRow[]>('screenshots', { required: true });

const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const error = ref('');

const remainingSlots = computed(() => Math.max(0, MAX_SCREENSHOTS - screenshots.value.length));

function openFileDialog() {
  fileInput.value?.click();
}

function addFiles(files: FileList | File[]) {
  error.value = '';
  const imageFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));

  if (imageFiles.length !== Array.from(files).length) {
    error.value = 'Можно добавлять только изображения.';
  }

  if (!imageFiles.length) return;
  if (remainingSlots.value <= 0) {
    error.value = `Можно загрузить максимум ${MAX_SCREENSHOTS} скриншотов.`;
    return;
  }

  const acceptedFiles = imageFiles.slice(0, remainingSlots.value);
  if (acceptedFiles.length < imageFiles.length) {
    error.value = `Добавлены только первые ${remainingSlots.value} файла. Максимум ${MAX_SCREENSHOTS} скриншотов.`;
  }

  screenshots.value.push(
    ...acceptedFiles.map((file) => ({
      title: '',
      file,
      previewUrl: URL.createObjectURL(file),
    }))
  );
}

function addFilesFromClipboard(event: ClipboardEvent) {
  const items = Array.from(event.clipboardData?.items ?? []);
  const files = items
    .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
    .map((item) => item.getAsFile())
    .filter((file): file is File => file instanceof File);

  if (!files.length) return;

  event.preventDefault();
  addFiles(files);
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement;
  if (input.files) {
    addFiles(input.files);
  }
  input.value = '';
}

function onDrop(event: DragEvent) {
  dragging.value = false;
  const files = event.dataTransfer?.files;
  if (files) {
    addFiles(files);
  }
}

function removeScreenshot(index: number) {
  const [removed] = screenshots.value.splice(index, 1);
  if (removed?.previewUrl) {
    URL.revokeObjectURL(removed.previewUrl);
  }
}

function previewSrc(shot: ScreenshotRow): string {
  return shot.previewUrl || shot.url || '';
}

onBeforeUnmount(() => {
  window.removeEventListener('paste', addFilesFromClipboard);
  screenshots.value.forEach((shot) => {
    if (shot.previewUrl) {
      URL.revokeObjectURL(shot.previewUrl);
    }
  });
});

onMounted(() => {
  window.addEventListener('paste', addFilesFromClipboard);
});
</script>

<template>
  <Card>
    <CardHeader>
      <div class="flex items-center justify-between gap-3">
        <div>
          <CardTitle class="text-base">Скриншоты</CardTitle>
          <p class="mt-1 text-sm text-muted-foreground">
            До {{ MAX_SCREENSHOTS }} изображений. При сохранении они будут сжаты до 1280 px.
          </p>
        </div>
        <div class="text-sm text-muted-foreground">
          {{ screenshots.length }}/{{ MAX_SCREENSHOTS }}
        </div>
      </div>
    </CardHeader>
    <CardContent class="space-y-4">
      <button
        type="button"
        :class="cn(
          'flex min-h-36 w-full flex-col items-center justify-center gap-3 rounded-md border border-dashed border-border bg-muted/20 px-4 py-6 text-center transition-colors',
          dragging && 'border-primary bg-primary/5',
          remainingSlots <= 0 && 'cursor-not-allowed opacity-60'
        )"
        :disabled="remainingSlots <= 0"
        @click="openFileDialog"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <span class="flex h-11 w-11 items-center justify-center rounded-md border bg-background text-primary">
          <span class="text-lg leading-none">+</span>
        </span>
        <span class="space-y-1">
          <span class="block text-sm font-semibold">
            Перетащите скриншоты сюда или выберите файлы
          </span>
          <span class="block text-sm text-muted-foreground">
            JPG, PNG, WebP, перетаскивание или вставка из буфера. Осталось мест: {{ remainingSlots }}.
          </span>
        </span>
      </button>

      <input
        ref="fileInput"
        class="hidden"
        type="file"
        accept="image/*"
        multiple
        @change="onFileChange"
      >

      <p v-if="error" class="text-sm text-destructive">
        {{ error }}
      </p>

      <div v-if="screenshots.length" class="grid gap-3 md:grid-cols-2">
        <div
          v-for="(shot, index) in screenshots"
          :key="shot.previewUrl || shot.url || index"
          class="overflow-hidden rounded-md border bg-card"
        >
          <div class="relative aspect-video bg-muted">
            <img
              v-if="previewSrc(shot)"
              :src="previewSrc(shot)"
              alt=""
              class="h-full w-full object-cover"
            >
            <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground">
              <span class="text-sm">Нет превью</span>
            </div>
            <Button
              type="button"
              variant="outline"
              size="icon"
              class="absolute right-2 top-2 h-8 w-8 bg-background/95"
              title="Удалить скриншот"
              aria-label="Удалить скриншот"
              @click="removeScreenshot(index)"
            >
              ×
            </Button>
          </div>
          <div class="space-y-2 p-3">
            <Input
              v-model="shot.title"
              type="text"
              maxlength="255"
              placeholder="Название скриншота (необязательно)"
            />
            <p class="text-xs text-muted-foreground">
              {{ shot.file ? shot.file.name : 'Загруженный скриншот' }}
            </p>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
