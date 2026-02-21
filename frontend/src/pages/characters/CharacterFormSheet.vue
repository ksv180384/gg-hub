<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Avatar,
  Button,
  Input,
  Label,
  SelectContent,
  SelectItem,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  Sheet,
} from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import type { Game, GameClass, Localization, Server } from '@/shared/api/gamesApi';
import {
  charactersApi,
  type Character,
  type CreateCharacterPayload,
  type UpdateCharacterPayload,
} from '@/shared/api/charactersApi';

const props = defineProps<{
  open: boolean;
  gameFull: Game | null;
  gameLoading: boolean;
  editingCharacter: Character | null;
  gameId: number;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'saved'): void;
}>();

const name = ref('');
const localizationId = ref('');
const serverId = ref('');
const selectedClassIds = ref<number[]>([]);
const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const removeAvatar = ref(false);
const formSaving = ref(false);
const formError = ref('');
const avatarDragOver = ref(false);
const avatarFileInputRef = ref<HTMLInputElement | null>(null);

const localizations = computed((): Localization[] => props.gameFull?.localizations ?? []);
const gameClasses = computed((): GameClass[] => props.gameFull?.game_classes ?? []);
const maxClassesPerCharacter = computed(() => props.gameFull?.max_classes_per_character ?? 1);
const servers = computed((): Server[] => {
  if (!localizationId.value) return [];
  const loc = localizations.value.find((l) => String(l.id) === localizationId.value);
  return loc?.servers ?? [];
});

const canSubmit = computed(
  () => name.value.trim() !== '' && localizationId.value !== '' && serverId.value !== ''
);

const avatarDisplayUrl = computed(() => {
  if (avatarPreview.value) return avatarPreview.value;
  if (props.editingCharacter && !removeAvatar.value) {
    return props.editingCharacter.avatar_url ?? null;
  }
  return null;
});

watch(
  () => [props.open, props.editingCharacter] as const,
  ([open, editing]) => {
    if (!open) return;
    if (editing) {
      name.value = editing.name;
      localizationId.value = String(editing.localization_id);
      serverId.value = String(editing.server_id);
      selectedClassIds.value = editing.game_classes?.map((c) => c.id) ?? [];
      avatarFile.value = null;
      avatarPreview.value = null;
      removeAvatar.value = false;
    } else {
      name.value = '';
      localizationId.value = '';
      serverId.value = '';
      selectedClassIds.value = [];
      avatarFile.value = null;
      avatarPreview.value = null;
      removeAvatar.value = false;
    }
    formError.value = '';
  }
);

watch(localizationId, () => {
  serverId.value = '';
});

function toggleClassId(id: number) {
  const idx = selectedClassIds.value.indexOf(id);
  if (idx === -1) {
    if (selectedClassIds.value.length < maxClassesPerCharacter.value) {
      selectedClassIds.value = [...selectedClassIds.value, id];
    }
  } else {
    selectedClassIds.value = selectedClassIds.value.filter((x) => x !== id);
  }
}

function setAvatarFile(file: File | null) {
  if (avatarPreview.value) URL.revokeObjectURL(avatarPreview.value);
  avatarFile.value = file ?? null;
  avatarPreview.value = file ? URL.createObjectURL(file) : null;
  if (file) removeAvatar.value = false;
}

function onAvatarChange(e: Event) {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file?.type.startsWith('image/')) setAvatarFile(file);
  target.value = '';
}

function onAvatarDrop(e: DragEvent) {
  avatarDragOver.value = false;
  const file = e.dataTransfer?.files?.[0];
  if (file?.type.startsWith('image/')) setAvatarFile(file);
  e.preventDefault();
}

function onAvatarDragOver(e: DragEvent) {
  avatarDragOver.value = true;
  e.preventDefault();
  e.dataTransfer && (e.dataTransfer.dropEffect = 'copy');
}

function onAvatarDragLeave() {
  avatarDragOver.value = false;
}

function openAvatarFilePicker() {
  avatarFileInputRef.value?.click();
}

async function submitForm() {
  if (!canSubmit.value || !props.gameId) return;
  formError.value = '';
  formSaving.value = true;
  try {
    if (props.editingCharacter) {
      const payload: UpdateCharacterPayload = {
        name: name.value.trim(),
        localization_id: Number(localizationId.value),
        server_id: Number(serverId.value),
        remove_avatar: removeAvatar.value,
        game_class_ids: selectedClassIds.value,
      };
      if (avatarFile.value) payload.avatar = avatarFile.value;
      await charactersApi.updateCharacter(props.editingCharacter.id, payload);
    } else {
      const payload: CreateCharacterPayload = {
        game_id: props.gameId,
        name: name.value.trim(),
        localization_id: Number(localizationId.value),
        server_id: Number(serverId.value),
        game_class_ids: selectedClassIds.value,
      };
      if (avatarFile.value) payload.avatar = avatarFile.value;
      await charactersApi.createCharacter(payload);
    }
    emit('update:open', false);
    emit('saved');
  } catch (err) {
    formError.value = err instanceof Error ? err.message : 'Ошибка сохранения';
  } finally {
    formSaving.value = false;
  }
}
</script>

<template>
  <Sheet :open="open" @update:open="emit('update:open', $event)" class="max-w-md">
    <template #title>Персонаж</template>
    <template #description>
      {{ editingCharacter != null ? 'Редактирование' : 'Новый персонаж' }}
    </template>
    <form v-if="open" class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitForm">
      <div class="min-h-0 flex-1 overflow-y-auto">
        <div class="flex flex-col gap-4 pb-4">
          <h2 class="text-lg font-semibold">
            {{ editingCharacter != null ? 'Редактирование персонажа' : 'Новый персонаж' }}
          </h2>
          <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>

          <div class="space-y-2">
        <Label for="char-name">Имя персонажа <span class="text-destructive">*</span></Label>
        <Input
          id="char-name"
          v-model="name"
          type="text"
          required
          maxlength="255"
          placeholder="Введите имя"
        />
      </div>

      <div class="space-y-2">
        <Label for="char-loc">Локализация <span class="text-destructive">*</span></Label>
        <SelectRoot v-model="localizationId" required :disabled="gameLoading">
          <SelectTrigger id="char-loc" class="w-full">
            <SelectValue placeholder="Выберите локализацию" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="loc in localizations" :key="loc.id" :value="String(loc.id)">
              {{ loc.name }}
            </SelectItem>
          </SelectContent>
        </SelectRoot>
      </div>

      <div class="space-y-2">
        <Label for="char-server">Сервер <span class="text-destructive">*</span></Label>
        <SelectRoot v-model="serverId" required :disabled="!localizationId || gameLoading">
          <SelectTrigger id="char-server" class="w-full">
            <SelectValue placeholder="Выберите сервер" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="srv in servers" :key="srv.id" :value="String(srv.id)">
              {{ srv.name }}
            </SelectItem>
          </SelectContent>
        </SelectRoot>
      </div>

      <div v-if="gameClasses.length" class="space-y-2">
        <Label>Классы (необязательно, макс. {{ maxClassesPerCharacter }})</Label>
        <div class="flex flex-wrap gap-3">
          <label
            v-for="gc in gameClasses"
            :key="gc.id"
            class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors hover:bg-muted/50"
            :class="{ 'ring-2 ring-primary': selectedClassIds.includes(gc.id) }"
          >
            <input
              type="checkbox"
              :checked="selectedClassIds.includes(gc.id)"
              :disabled="!selectedClassIds.includes(gc.id) && selectedClassIds.length >= maxClassesPerCharacter"
              class="rounded border-input"
              @change="toggleClassId(gc.id)"
            />
            <img
              v-if="gc.image_thumb || gc.image"
              :src="storageImageUrl(gc.image_thumb || gc.image)"
              :alt="gc.name_ru || gc.name"
              class="h-6 w-6 rounded object-cover"
            />
            <span>{{ gc.name_ru || gc.name }}</span>
          </label>
        </div>
      </div>

      <div class="space-y-2">
        <Label>Аватар (необязательно)</Label>
        <input
          ref="avatarFileInputRef"
          type="file"
          accept="image/jpeg,image/png,image/gif,image/webp"
          class="sr-only"
          aria-hidden="true"
          tabindex="-1"
          @change="onAvatarChange"
        />
        <div
          role="button"
          tabindex="0"
          class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed py-8 transition-colors cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          :class="avatarDragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/20 hover:border-muted-foreground/40 hover:bg-muted/30'"
          @drop="onAvatarDrop"
          @dragover="onAvatarDragOver"
          @dragleave="onAvatarDragLeave"
          @click="openAvatarFilePicker"
          @keydown.enter.prevent="openAvatarFilePicker"
          @keydown.space.prevent="openAvatarFilePicker"
        >
          <Avatar
            v-if="avatarDisplayUrl"
            :src="avatarDisplayUrl"
            alt=""
            fallback="??"
            class="h-20 w-20 shrink-0 ring-2 ring-muted"
          />
          <template v-else>
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-muted/50 text-muted-foreground">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                <circle cx="9" cy="9" r="2"/>
                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
              </svg>
            </div>
            <p class="text-center text-sm text-muted-foreground">Перетащите сюда или нажмите</p>
          </template>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
          <label
            v-if="editingCharacter != null && (avatarDisplayUrl || removeAvatar)"
            class="flex items-center gap-2 text-muted-foreground"
          >
            <input v-model="removeAvatar" type="checkbox" class="rounded border-input" />
            Удалить аватар
          </label>
          <button
            v-if="avatarFile"
            type="button"
            class="text-destructive hover:underline"
            @click="setAvatarFile(null)"
          >
            Удалить выбранное изображение
          </button>
        </div>
      </div>
        </div>
      </div>

      <div class="shrink-0 border-t bg-background pt-4">
        <div class="flex gap-2">
          <Button type="submit" :disabled="!canSubmit || formSaving">
            {{ formSaving ? 'Сохранение…' : (editingCharacter != null ? 'Сохранить' : 'Добавить') }}
          </Button>
          <Button type="button" variant="outline" @click="emit('update:open', false)">Отмена</Button>
        </div>
      </div>
    </form>
  </Sheet>
</template>
