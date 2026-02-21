<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
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
} from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import type { Game, GameClass, Localization, Server } from '@/shared/api/gamesApi';
import {
  charactersApi,
  type Character,
  type CreateCharacterPayload,
  type UpdateCharacterPayload,
} from '@/shared/api/charactersApi';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';

const props = defineProps<{
  gameFull: Game | null;
  gameLoading: boolean;
  editingCharacter: Character | null;
  gameId: number;
}>();

const emit = defineEmits<{
  (e: 'saved'): void;
  (e: 'cancel'): void;
}>();

const name = ref('');
const localizationId = ref('');
const serverId = ref('');
const selectedClassIds = ref<number[]>([]);
const allTags = ref<Tag[]>([]);
const selectedTagIds = ref<number[]>([]);
const tagToAddFromSelect = ref('');
const newTagName = ref('');
const creatingTag = ref(false);
const createTagError = ref<string | null>(null);
const addingNewTag = ref(false);
const avatarFile = ref<File | null>(null);
const avatarPreview = ref<string | null>(null);
const removeAvatar = ref(false);
const isMain = ref(false);
const formSaving = ref(false);
const formError = ref('');
const avatarDragOver = ref(false);
const avatarFileInputRef = ref<HTMLInputElement | null>(null);
const serverSearch = ref('');

const localizations = computed((): Localization[] => props.gameFull?.localizations ?? []);
const gameClasses = computed((): GameClass[] => props.gameFull?.game_classes ?? []);
const maxClassesPerCharacter = computed(() => props.gameFull?.max_classes_per_character ?? 1);
const servers = computed((): Server[] => {
  if (!localizationId.value) return [];
  const loc = localizations.value.find((l) => String(l.id) === localizationId.value);
  return loc?.servers ?? [];
});

const filteredServers = computed((): Server[] => {
  const list = servers.value;
  const q = serverSearch.value.trim().toLowerCase();
  if (!q) return list;
  const filtered = list.filter((s) => s.name.toLowerCase().includes(q));
  const id = serverId.value;
  if (id && !filtered.some((s) => String(s.id) === id)) {
    const selected = list.find((s) => String(s.id) === id);
    if (selected) return [selected, ...filtered];
  }
  return filtered;
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

const tagsNotSelected = computed(() =>
  allTags.value.filter((t) => !selectedTagIds.value.includes(t.id))
);

function resetForm(editing: Character | null) {
  if (editing) {
    name.value = editing.name;
    localizationId.value = String(editing.localization_id);
    selectedClassIds.value = editing.game_classes?.map((c) => c.id) ?? [];
    selectedTagIds.value = editing.tags?.map((t) => t.id) ?? [];
    avatarFile.value = null;
    avatarPreview.value = null;
    removeAvatar.value = false;
    isMain.value = editing.is_main ?? false;
    serverId.value = '';
    nextTick().then(() => {
      serverId.value = String(editing.server_id);
    });
  } else {
    name.value = '';
    localizationId.value = '';
    serverId.value = '';
    selectedClassIds.value = [];
    selectedTagIds.value = [];
    avatarFile.value = null;
    avatarPreview.value = null;
    removeAvatar.value = false;
    isMain.value = false;
  }
  serverSearch.value = '';
  formError.value = '';
}

function loadTags() {
  tagsApi.getTags(false).then((list) => { allTags.value = list; }).catch(() => { allTags.value = []; });
}

watch(
  () => props.editingCharacter,
  (editing) => {
    resetForm(editing ?? null);
  },
  { immediate: true }
);

onMounted(() => {
  loadTags();
});

watch(() => props.gameFull, (g) => {
  if (g) loadTags();
});

function toggleTag(tagId: number) {
  const idx = selectedTagIds.value.indexOf(tagId);
  if (idx >= 0) {
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
  } else {
    selectedTagIds.value = [...selectedTagIds.value, tagId];
  }
}

function onAddTagFromSelect(value?: string) {
  const raw = value ?? tagToAddFromSelect.value;
  const id = raw ? Number(raw) : 0;
  if (id && !selectedTagIds.value.includes(id)) {
    selectedTagIds.value = [...selectedTagIds.value, id];
    tagToAddFromSelect.value = '';
  }
}

function cancelNewTag() {
  addingNewTag.value = false;
  newTagName.value = '';
  createTagError.value = null;
}

async function createAndAddTag() {
  const trimName = newTagName.value.trim();
  if (!trimName || creatingTag.value) return;
  creatingTag.value = true;
  createTagError.value = null;
  try {
    const tag = await tagsApi.createTag({ name: trimName });
    if (!allTags.value.some((t) => t.id === tag.id)) {
      allTags.value = [...allTags.value, tag];
    }
    newTagName.value = '';
    addingNewTag.value = false;
    tagToAddFromSelect.value = '';
  } catch (e) {
    createTagError.value = e instanceof Error ? e.message : 'Не удалось создать тег';
  } finally {
    creatingTag.value = false;
  }
}

watch(localizationId, () => {
  serverId.value = '';
  serverSearch.value = '';
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
        is_main: isMain.value,
        game_class_ids: selectedClassIds.value,
        tag_ids: selectedTagIds.value,
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
        tag_ids: selectedTagIds.value,
      };
      if (avatarFile.value) payload.avatar = avatarFile.value;
      await charactersApi.createCharacter(payload);
    }
    emit('saved');
  } catch (err) {
    formError.value = err instanceof Error ? err.message : 'Ошибка сохранения';
  } finally {
    formSaving.value = false;
  }
}
</script>

<template>
  <form class="flex flex-col gap-6" @submit.prevent="submitForm">
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
        <SelectContent class="p-0">
          <div class="sticky top-0 z-10 border-b bg-popover p-1.5">
            <Input
              v-model="serverSearch"
              type="text"
              placeholder="Поиск сервера..."
              class="h-8 text-sm"
              @keydown.stop
            />
          </div>
          <SelectItem
            v-for="srv in filteredServers"
            :key="srv.id"
            :value="String(srv.id)"
          >
            {{ srv.name }}
          </SelectItem>
        </SelectContent>
      </SelectRoot>
    </div>

    <div v-if="editingCharacter != null" class="flex items-center gap-2">
      <input
        id="char-is-main"
        v-model="isMain"
        type="checkbox"
        class="h-4 w-4 rounded border-input"
      />
      <Label for="char-is-main" class="cursor-pointer font-normal">
        Основной персонаж в игре (только один в игре, можно менять)
      </Label>
    </div>

    <div class="space-y-3">
      <Label>Теги персонажа</Label>
      <p class="text-xs text-muted-foreground">
        Выберите теги для персонажа или добавьте новый — он станет доступен всем.
      </p>
      <div v-if="selectedTagIds.length" class="flex flex-wrap gap-2">
        <label
          v-for="tag in allTags.filter((t) => selectedTagIds.includes(t.id))"
          :key="tag.id"
          class="flex cursor-pointer items-center gap-1.5 rounded-md border border-input px-3 py-1.5 text-sm hover:bg-accent"
          :class="{ 'bg-primary text-primary-foreground': selectedTagIds.includes(tag.id) }"
        >
          <input
            type="checkbox"
            :checked="true"
            class="sr-only"
            @change="toggleTag(tag.id)"
          >
          {{ tag.name }}
        </label>
      </div>
      <div class="space-y-1">
        <Label for="char-tag-select" class="text-muted-foreground">Добавить тег</Label>
        <SelectRoot
          id="char-tag-select"
          v-model="tagToAddFromSelect"
          @update:model-value="(v) => onAddTagFromSelect(v)"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Выберите тег" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="tag in tagsNotSelected"
              :key="tag.id"
              :value="String(tag.id)"
            >
              {{ tag.name }}
            </SelectItem>
            <div
              class="border-t border-border p-1"
              @mousedown.prevent
            >
              <template v-if="!addingNewTag">
                <button
                  type="button"
                  class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground"
                  @click="addingNewTag = true"
                >
                  <span class="text-base leading-none">+</span>
                  Добавить новый
                </button>
              </template>
              <template v-else>
                <div class="flex flex-col gap-2 p-1">
                  <Input
                    v-model="newTagName"
                    placeholder="Название тега"
                    class="h-8 text-sm"
                    :disabled="creatingTag"
                    @keydown.enter.prevent="createAndAddTag"
                  />
                  <div class="flex gap-1">
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      class="flex-1"
                      :disabled="!newTagName.trim() || creatingTag"
                      @click="createAndAddTag"
                    >
                      {{ creatingTag ? '…' : 'Создать' }}
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      :disabled="creatingTag"
                      @click="cancelNewTag"
                    >
                      Отмена
                    </Button>
                  </div>
                  <p v-if="createTagError" class="text-xs text-destructive">
                    {{ createTagError }}
                  </p>
                </div>
              </template>
            </div>
          </SelectContent>
        </SelectRoot>
      </div>
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

    <div class="flex flex-wrap gap-2">
      <Button type="submit" :disabled="!canSubmit || formSaving">
        {{ formSaving ? 'Сохранение…' : (editingCharacter != null ? 'Сохранить' : 'Добавить') }}
      </Button>
      <Button type="button" variant="outline" @click="emit('cancel')">
        Отмена
      </Button>
    </div>
  </form>
</template>
