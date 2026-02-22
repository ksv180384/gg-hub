<script setup lang="ts">
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Label,
  Avatar,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { gamesApi, type Game, type Localization, type Server } from '@/shared/api/gamesApi';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

type TabId = 'settings' | 'about' | 'charter' | 'application';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const guildId = computed(() => Number(route.params.id));
const guild = ref<Guild | null>(null);
const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});

const activeTab = ref<TabId>('settings');

const name = ref('');
const selectedLocalizationId = ref<string>('');
const selectedServerId = ref<string>('');
const showRosterToAll = ref(false);
const aboutText = ref('');
const charterText = ref('');

const logoFile = ref<File | null>(null);
const logoPreview = ref<string | null>(null);
const removeLogo = ref(false);
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const allTags = ref<Tag[]>([]);
const selectedTagIds = ref<number[]>([]);
const tagToAddFromSelect = ref('');
const newTagName = ref('');
const creatingTag = ref(false);
const createTagError = ref<string | null>(null);
const addingNewTag = ref(false);

const games = ref<Game[]>([]);
const servers = ref<Server[]>([]);
const selectedGame = computed(() =>
  guild.value ? games.value.find((g) => g.id === guild.value!.game_id) : null
);
const availableLocalizations = computed(() => {
  const g = selectedGame.value;
  if (!g?.localizations) return [];
  return g.localizations.filter((l) => l.is_active !== false);
});

function getTag(n: string): string {
  return n
    .split(/\s+/)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

function setLogoFile(file: File | null) {
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
  logoFile.value = file ?? null;
  logoPreview.value = file ? URL.createObjectURL(file) : null;
  removeLogo.value = false;
}

async function onLogoChange(e: Event) {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  target.value = '';
  if (file?.type.startsWith('image/')) {
    setLogoFile(file);
    await uploadLogo();
  }
}

async function onLogoDrop(e: DragEvent) {
  dragOver.value = false;
  e.preventDefault();
  const file = e.dataTransfer?.files?.[0];
  if (file?.type.startsWith('image/')) {
    setLogoFile(file);
    await uploadLogo();
  }
}

function onLogoDragOver(e: DragEvent) {
  dragOver.value = true;
  e.preventDefault();
  e.dataTransfer && (e.dataTransfer.dropEffect = 'copy');
}

function onLogoDragLeave() {
  dragOver.value = false;
}

async function uploadLogo() {
  if (!guild.value || !logoFile.value) return;
  saving.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.updateGuild(guild.value.id, { logo: logoFile.value });
    setLogoFile(null);
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Не удалось загрузить логотип';
  } finally {
    saving.value = false;
  }
}

function openFilePicker() {
  fileInputRef.value?.click();
}

async function loadGuild() {
  if (!guildId.value) return;
  loading.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.getGuildForSettings(guildId.value);
    if ((guild.value as { owner_id?: number }).owner_id !== authStore.user?.id) {
      router.replace('/guilds');
      return;
    }
    name.value = guild.value.name;
    selectedLocalizationId.value = String(guild.value.localization_id);
    selectedServerId.value = String(guild.value.server_id);
    showRosterToAll.value = guild.value.show_roster_to_all ?? false;
    aboutText.value = guild.value.about_text ?? '';
    charterText.value = guild.value.charter_text ?? '';
    selectedTagIds.value = (guild.value.tags ?? []).map((t) => t.id);
  } catch (e: unknown) {
    const err = e as { status?: number };
    if (err.status === 403) {
      router.replace('/guilds');
      return;
    }
    error.value = 'Не удалось загрузить гильдию';
  } finally {
    loading.value = false;
  }
}

async function loadGames() {
  try {
    games.value = await gamesApi.getGames();
  } catch {
    games.value = [];
  }
}

async function loadServers() {
  const g = guild.value;
  if (!g) return;
  try {
    servers.value = await gamesApi.getServers(g.game_id, g.localization_id);
  } catch {
    servers.value = [];
  }
}

watch(guild, (g) => {
  if (g) loadServers();
});

watch(selectedLocalizationId, (locId) => {
  const g = guild.value;
  if (!g || !locId) return;
  gamesApi.getServers(g.game_id, Number(locId)).then((list) => {
    servers.value = list;
  }).catch(() => {
    servers.value = [];
  });
});

function toggleTag(tagId: number) {
  const idx = selectedTagIds.value.indexOf(tagId);
  if (idx >= 0) {
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
  } else {
    selectedTagIds.value = [...selectedTagIds.value, tagId];
  }
}

const tagsNotSelected = computed(() =>
  allTags.value.filter((t) => !selectedTagIds.value.includes(t.id))
);
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
  const name = newTagName.value.trim();
  if (!name || creatingTag.value) return;
  creatingTag.value = true;
  createTagError.value = null;
  try {
    const tag = await tagsApi.createTag({ name });
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

async function saveSettings() {
  if (!guild.value) return;
  saving.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    guild.value = await guildsApi.updateGuild(guild.value.id, {
      name: name.value.trim(),
      localization_id: Number(selectedLocalizationId.value),
      server_id: Number(selectedServerId.value),
      show_roster_to_all: showRosterToAll.value,
      tag_ids: selectedTagIds.value,
    });
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> };
    if (err.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(err.errors).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : String(v)])
      ) as Record<string, string>;
    }
    error.value = err.message ?? 'Не удалось сохранить';
  } finally {
    saving.value = false;
  }
}

async function saveAbout() {
  if (!guild.value) return;
  saving.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.updateGuild(guild.value.id, { about_text: aboutText.value || null });
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Не удалось сохранить';
  } finally {
    saving.value = false;
  }
}

async function saveCharter() {
  if (!guild.value) return;
  saving.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.updateGuild(guild.value.id, { charter_text: charterText.value || null });
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Не удалось сохранить';
  } finally {
    saving.value = false;
  }
}

async function removeLogoAndSave() {
  if (!guild.value) return;
  removeLogo.value = true;
  setLogoFile(null);
  saving.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.updateGuild(guild.value.id, { remove_logo: true });
    removeLogo.value = false;
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Не удалось удалить логотип';
  } finally {
    saving.value = false;
  }
}

const logoDisplayUrl = computed(() => {
  if (removeLogo.value) return null;
  if (logoPreview.value) return logoPreview.value;
  return guild.value?.logo_url ? storageImageUrl(guild.value.logo_url) : null;
});

onMounted(async () => {
  loadGames();
  loadGuild();
  try {
    allTags.value = await tagsApi.getTags(false);
  } catch {
    allTags.value = [];
  }
});

const tabs: { id: TabId; label: string }[] = [
  { id: 'settings', label: 'Настройки' },
  { id: 'about', label: 'О гильдии' },
  { id: 'charter', label: 'Устав' },
  { id: 'application', label: 'Форма заявки' },
];
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <div class="mb-6 flex items-center gap-4">
        <Button variant="ghost" size="sm" @click="router.push({ name: 'guilds' })">
          ← К списку гильдий
        </Button>
      </div>

      <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ error }}
      </div>

      <div v-if="loading" class="text-muted-foreground">Загрузка…</div>

      <template v-else-if="guild">
        <div class="flex flex-col gap-6 md:flex-row md:items-start">
          <!-- Левая колонка: название, логотип 290×290 и под ним лидер / участники -->
          <div class="flex w-full shrink-0 flex-col items-center order-1 md:order-1 md:w-[290px]">
            <h1 class="mb-3 w-full text-center text-xl font-bold md:text-2xl">{{ guild.name }}</h1>
            <input
              ref="fileInputRef"
              type="file"
              :accept="ACCEPT_IMAGES"
              class="sr-only"
              @change="onLogoChange"
            />
            <div
              role="button"
              tabindex="0"
              aria-label="Загрузить логотип гильдии"
              class="relative flex h-[290px] w-full max-w-[290px] cursor-pointer shrink-0 flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              :class="
                dragOver
                  ? 'border-primary bg-primary/5'
                  : 'border-muted-foreground/30 bg-muted/30 hover:border-muted-foreground/50 hover:bg-muted/50'
              "
              @click="openFilePicker"
              @keydown.enter.prevent="openFilePicker"
              @keydown.space.prevent="openFilePicker"
              @dragover.prevent="onLogoDragOver"
              @dragleave="onLogoDragLeave"
              @drop.prevent="onLogoDrop"
            >
              <template v-if="logoDisplayUrl">
                <img
                  :src="logoDisplayUrl"
                  alt="Логотип гильдии"
                  class="absolute inset-0 h-full w-full object-cover"
                />
                <div class="absolute inset-0 flex items-end justify-center rounded-lg bg-black/40 p-2 opacity-0 transition-opacity hover:opacity-100">
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="text-xs"
                    :disabled="saving"
                    @click.stop="removeLogoAndSave"
                  >
                    Удалить
                  </Button>
                </div>
              </template>
              <template v-else>
                <span v-if="saving" class="text-sm text-muted-foreground">Загрузка…</span>
                <span v-else class="px-3 text-center text-sm text-muted-foreground">
                  Перетащите изображение сюда или нажмите для выбора
                </span>
              </template>
            </div>
            <div class="mt-3 flex w-full max-w-[290px] flex-col items-center gap-1 text-center text-sm">
              <div class="font-medium text-foreground">
                Лидер: {{ guild.leader?.name ?? '—' }}
              </div>
              <div class="text-muted-foreground">
                Участников: {{ guild.members_count ?? 0 }}
              </div>
            </div>
          </div>

          <!-- Правая колонка: табы и контент -->
          <div class="min-w-0 flex-1 order-2 md:order-2">
            <div class="mb-4 flex flex-wrap gap-1 border-b">
          <button
            v-for="t in tabs"
            :key="t.id"
            type="button"
            :aria-label="t.label"
            class="flex items-center justify-center gap-2 rounded-t-md border-b-2 px-3 py-2 text-sm font-medium transition-colors md:justify-start md:px-4"
            :class="
              activeTab === t.id
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            @click="activeTab = t.id"
          >
            <!-- Иконки только на мобильной (чёрно-белые, currentColor) -->
            <span class="flex shrink-0 md:hidden" aria-hidden="true">
              <!-- Настройки (шестерёнка) -->
              <svg v-if="t.id === 'settings'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1Z" />
              </svg>
              <!-- О гильдии (инфо) -->
              <svg v-else-if="t.id === 'about'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 16v-4" />
                <path d="M12 8h.01" />
              </svg>
              <!-- Устав (документ) -->
              <svg v-else-if="t.id === 'charter'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <path d="M14 2v6h6" />
                <path d="M16 13H8" />
                <path d="M16 17H8" />
                <path d="M10 9H8" />
              </svg>
              <!-- Форма заявки (клипборд/форма) -->
              <svg v-else-if="t.id === 'application'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                <path d="M12 11h4" />
                <path d="M12 16h4" />
                <path d="M8 11h.01" />
                <path d="M8 16h.01" />
              </svg>
            </span>
            <span class="hidden md:inline">{{ t.label }}</span>
          </button>
            </div>

            <!-- Вкладка: Настройки -->
        <Card v-show="activeTab === 'settings'" class="mb-6">
          <CardHeader>
            <CardTitle>Настройки</CardTitle>
          </CardHeader>
          <CardContent class="space-y-6">
            <div class="space-y-2">
              <Label for="settings-name">Название гильдии *</Label>
              <Input id="settings-name" v-model="name" />
              <p v-if="fieldErrors.name" class="text-sm text-destructive">{{ fieldErrors.name }}</p>
            </div>

            <div class="space-y-2">
              <Label>Локализация *</Label>
              <SelectRoot v-model="selectedLocalizationId" :disabled="!availableLocalizations.length">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Локализация" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="loc in availableLocalizations"
                    :key="loc.id"
                    :value="String(loc.id)"
                  >
                    {{ loc.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
              <p v-if="fieldErrors.localization_id" class="text-sm text-destructive">
                {{ fieldErrors.localization_id }}
              </p>
            </div>

            <div class="space-y-2">
              <Label>Сервер *</Label>
              <SelectRoot v-model="selectedServerId" :disabled="!servers.length">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Сервер" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="srv in servers" :key="srv.id" :value="String(srv.id)">
                    {{ srv.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
              <p v-if="fieldErrors.server_id" class="text-sm text-destructive">
                {{ fieldErrors.server_id }}
              </p>
            </div>

            <div class="flex items-center gap-2">
              <input
                id="show-roster"
                v-model="showRosterToAll"
                type="checkbox"
                class="h-4 w-4 rounded border-input"
              />
              <Label for="show-roster" class="cursor-pointer font-normal">
                Показывать состав гильдии всем пользователям
              </Label>
            </div>

            <div class="space-y-3">
              <Label>Теги гильдии</Label>
              <p class="text-xs text-muted-foreground">
                Выберите теги для гильдии или добавьте новый — он станет доступен всем.
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
                <Label for="tag-select" class="text-muted-foreground">Добавить тег</Label>
                <SelectRoot
                  id="tag-select"
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

            <Button :disabled="saving" @click="saveSettings">
              {{ saving ? 'Сохранение…' : 'Сохранить настройки' }}
            </Button>
          </CardContent>
        </Card>

        <!-- Вкладка: О гильдии -->
        <Card v-show="activeTab === 'about'" class="mb-6">
          <CardHeader>
            <CardTitle>О гильдии</CardTitle>
          </CardHeader>
          <CardContent class="space-y-6">
            <div class="space-y-2">
              <Label for="about-text">Текст «О гильдии»</Label>
              <textarea
                id="about-text"
                v-model="aboutText"
                rows="12"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="Расскажите о гильдии, целях и правилах…"
              />
            </div>

            <Button :disabled="saving" @click="saveAbout">
              {{ saving ? 'Сохранение…' : 'Сохранить' }}
            </Button>
          </CardContent>
        </Card>

        <!-- Вкладка: Устав -->
        <Card v-show="activeTab === 'charter'" class="mb-6">
          <CardHeader>
            <CardTitle>Устав</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="charter-text">Текст устава</Label>
              <textarea
                id="charter-text"
                v-model="charterText"
                rows="12"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="Устав гильдии…"
              />
            </div>
            <Button :disabled="saving" @click="saveCharter">
              {{ saving ? 'Сохранение…' : 'Сохранить' }}
            </Button>
          </CardContent>
        </Card>

        <!-- Вкладка: Форма заявки (пока пусто) -->
        <Card v-show="activeTab === 'application'" class="mb-6">
          <CardHeader>
            <CardTitle>Форма заявки</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground">
              Раздел в разработке. Здесь можно будет настроить поля формы заявки в гильдию.
            </p>
          </CardContent>
        </Card>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
