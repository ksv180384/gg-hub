<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button, Input, Label, Tooltip } from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import type { GameClass } from '@/shared/api/gamesApi';

const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

const props = defineProps<{
  gc: GameClass;
  isEditing: boolean;
  saving: boolean;
  deleting: boolean;
}>();

const emit = defineEmits<{
  (e: 'edit'): void;
  (e: 'save', payload: { name: string; name_ru: string | null; slug: string; image?: File; remove_image: boolean }): void;
  (e: 'cancel'): void;
  (e: 'delete'): void;
}>();

const name = ref('');
const nameRu = ref('');
const slug = ref('');
const imageFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const removeImage = ref(false);
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

watch(
  () => [props.isEditing, props.gc] as const,
  ([editing, gc]) => {
    if (editing && gc) {
      name.value = gc.name;
      nameRu.value = gc.name_ru ?? '';
      slug.value = gc.slug ?? '';
      imageFile.value = null;
      imagePreview.value = null;
      removeImage.value = false;
    }
  },
  { immediate: true }
);

function setImage(file: File | null) {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
  imageFile.value = file ?? null;
  imagePreview.value = file ? URL.createObjectURL(file) : null;
  if (file) removeImage.value = false;
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

function clearNewImage() {
  setImage(null);
}

function submit() {
  emit('save', {
    name: name.value.trim(),
    name_ru: nameRu.value.trim() || null,
    slug: slug.value.trim() || '',
    image: imageFile.value ?? undefined,
    remove_image: removeImage.value,
  });
}

const displayImage = () =>
  imagePreview.value || (props.gc.image && !removeImage.value ? storageImageUrl(props.gc.image_thumb || props.gc.image || '') : null);
</script>

<template>
  <li class="flex flex-wrap items-center gap-3 rounded-lg border p-3">
    <template v-if="isEditing">
      <div class="flex flex-col gap-4 w-full">
        <div class="flex flex-raw w-full flex-wrap items-end gap-3">

          <div class="flex flex-1 flex-col gap-2">
            <div class="space-y-1">
              <Label class="text-xs">Название (EN) *</Label>
              <Input v-model="name" class="" placeholder="Warrior" />
            </div>
            <div class="space-y-1">
              <Label class="text-xs">Название (RU)</Label>
              <Input v-model="nameRu" class="" placeholder="Воин" />
            </div>
            <div class="space-y-1">
              <Label class="text-xs">Слаг</Label>
              <Input v-model="slug" placeholder="auto" class="" />
            </div>
          </div>

          <div class="space-y-1">
            <Label class="text-xs">Изображение</Label>
            <input
              ref="fileInputRef"
              type="file"
              :accept="ACCEPT_IMAGES"
              class="sr-only"
              aria-hidden="true"
              tabindex="-1"
              @change="onImageChange"
            />
            <div
              role="button"
              tabindex="0"
              class="flex h-40 w-50 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed transition-colors cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
              :class="dragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/20 hover:border-muted-foreground/40 hover:bg-muted/30'"
              @drop="onDrop"
              @dragover="onDragOver"
              @dragleave="dragOver = false"
              @click="openFilePicker"
              @keydown.enter.prevent="openFilePicker"
              @keydown.space.prevent="openFilePicker"
            >
              <img
                v-if="displayImage()"
                :src="displayImage()!"
                alt=""
                class="h-14 w-14 rounded-lg object-cover"
              />
              <template v-else>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-muted-foreground">
                  <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                  <circle cx="9" cy="9" r="2"/>
                  <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                </svg>
                <span class="text-[10px] text-muted-foreground">Нажмите или перетащите</span>
              </template>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
              <label v-if="gc.image || imagePreview" class="flex items-center gap-2 text-muted-foreground">
                <input v-model="removeImage" type="checkbox" class="rounded border-input" />
                Удалить изображение
              </label>
              <button
                v-if="imageFile"
                type="button"
                class="text-primary underline hover:no-underline"
                @click="clearNewImage"
              >
                Отменить выбор файла
              </button>
            </div>
          </div>
        </div>
        <div class="flex gap-1">
          <Button type="button" size="sm" :disabled="saving || !name.trim()" @click="submit">
            {{ saving ? '...' : 'Сохранить' }}
          </Button>
          <Button type="button" size="sm" variant="ghost" @click="emit('cancel')">Отмена</Button>
        </div>
      </div>
    </template>
    <template v-else>
      <img
        v-if="gc.image_thumb || gc.image"
        :src="storageImageUrl(gc.image_thumb || gc.image)"
        alt=""
        class="h-10 w-10 rounded object-cover"
      />
      <span class="min-w-0 font-medium">
        {{ gc.name_ru ? `${gc.name} / ${gc.name_ru}` : gc.name }}
      </span>
      <span class="text-sm text-muted-foreground">({{ gc.slug }})</span>
      <div class="flex gap-1">
        <Tooltip content="Изменить">
          <Button
            type="button"
            size="icon"
            variant="ghost"
            class="h-9 w-9 shrink-0"
            :disabled="deleting"
            aria-label="Изменить"
            @click="emit('edit')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden>
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
              <path d="m15 5 4 4"/>
            </svg>
          </Button>
        </Tooltip>
        <Tooltip content="Удалить">
          <Button
            type="button"
            size="icon"
            variant="ghost"
            class="h-9 w-9 shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
            :disabled="deleting"
            aria-label="Удалить"
            @click="emit('delete')"
          >
            <svg v-if="!deleting" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden>
              <path d="M3 6h18"/>
              <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
              <line x1="10" x2="10" y1="11" y2="17"/>
              <line x1="14" x2="14" y1="11" y2="17"/>
            </svg>
            <span v-else class="text-xs">...</span>
          </Button>
        </Tooltip>
      </div>
    </template>
  </li>
</template>
