<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button, Input, Label } from '@/shared/ui';

const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

function slugFromName(s: string): string {
  return s
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

const props = defineProps<{
  submitting: boolean;
  error: string | null;
}>();

const emit = defineEmits<{
  (e: 'submit', payload: { name: string; name_ru: string; slug: string; image?: File }): void;
}>();

const name = ref('');
const nameRu = ref('');
const slug = ref('');
const image = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(name, (v) => {
  if (!slug.value) slug.value = slugFromName(v);
});

function setImage(file: File | null) {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
  image.value = file ?? null;
  imagePreview.value = file ? URL.createObjectURL(file) : null;
}

function onImageChange(e: Event) {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file?.type.startsWith('image/')) setImage(file);
  target.value = '';
}

function onDrop(e: DragEvent) {
  dragOver.value = false;
  const file = e.dataTransfer?.files?.[0];
  if (file?.type.startsWith('image/')) setImage(file);
  e.preventDefault();
}

function onDragOver(e: DragEvent) {
  dragOver.value = true;
  e.preventDefault();
  e.dataTransfer && (e.dataTransfer.dropEffect = 'copy');
}

function openFilePicker() {
  fileInputRef.value?.click();
}

function clearImage() {
  setImage(null);
}

function submit() {
  emit('submit', {
    name: name.value.trim(),
    name_ru: nameRu.value.trim(),
    slug: slug.value.trim(),
    image: image.value ?? undefined,
  });
}
</script>

<template>
  <form class="flex flex-wrap items-start gap-3 border-t pt-4" @submit.prevent="submit">
    <div class="flex flex-1 flex-col gap-3">
      <div>
        <Label for="class-name" class="text-xs">Название (EN) *</Label>
        <Input
          id="class-name"
          v-model="name"
          placeholder="Warrior"
          class="w-full"
        />
      </div>
      <div>
        <Label for="class-name-ru" class="text-xs">Название (RU)</Label>
        <Input id="class-name-ru" v-model="nameRu" placeholder="Воин" class="w-full" />
      </div>
      <div>
        <Label for="class-slug" class="text-xs">Слаг (необязательно)</Label>
        <Input id="class-slug" v-model="slug" placeholder="warrior" class="w-full" />
      </div>
      <Button class="w-32" type="submit" size="sm" :disabled="submitting || !name.trim()">
        {{ submitting ? 'Сохранение...' : 'Добавить класс' }}
      </Button>
    </div>
    <div class="space-y-1">
      <Label class="text-xs">Изображение (необязательно)</Label>
      <input
        ref="fileInputRef"
        type="file"
        :accept="ACCEPT_IMAGES"
        class="sr-only"
        aria-hidden="true"
        tabindex="-1"
        @change="onImageChange"
      />
      <div class="flex flex-col items-start gap-1">
        <div
          role="button"
          tabindex="0"
          class="flex h-40 w-full shrink-0 flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed transition-colors cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          :class="dragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/20 hover:border-muted-foreground/40 hover:bg-muted/30'"
          @drop="onDrop"
          @dragover="onDragOver"
          @dragleave="dragOver = false"
          @click="openFilePicker"
          @keydown.enter.prevent="openFilePicker"
          @keydown.space.prevent="openFilePicker"
        >
          <img v-if="imagePreview" :src="imagePreview" alt="" class="h-full w-full rounded-lg object-cover" />
          <template v-else>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-muted-foreground">
              <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
              <circle cx="9" cy="9" r="2"/>
              <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
            </svg>
            <span class="text-[10px] text-muted-foreground">Нажмите или перетащите</span>
          </template>
        </div>
        <button
          v-if="imagePreview"
          type="button"
          class="text-xs text-destructive hover:underline"
          @click="clearImage"
        >
          Удалить изображение
        </button>
      </div>
    </div>
    <p v-if="error" class="w-full text-sm text-destructive">{{ error }}</p>
  </form>
</template>
