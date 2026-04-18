<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Label,
  Badge,
  TagAddCombobox,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
  Tooltip,
} from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import RichTextEditor from '@/shared/ui/rich-text-editor/RichTextEditor.vue';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import {
  guildsApi,
  type Guild,
  type GuildApplicationFormFieldDto,
  type CreateGuildApplicationFormFieldPayload,
  type GuildRosterMember,
} from '@/shared/api/guildsApi';
import { gamesApi, type Game, type Server } from '@/shared/api/gamesApi';
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

/** Права текущего пользователя в этой гильдии (с сервера GET settings). */
const myPermissionSlugs = ref<string[]>([]);

/** Смена лидера гильдии (leader_character_id): только текущий лидер гильдии. */
const canChangeGuildLeader = ref(false);
const leaderRosterMembers = ref<GuildRosterMember[]>([]);
const selectedLeaderCharacterId = ref('');
/** Диалог подтверждения смены лидера: показывается перед запросом на сохранение. */
const leaderChangeDialogOpen = ref(false);

/**
 * Можно ли менять локализацию/сервер гильдии.
 * Разрешено только пока в гильдии один участник и он же — лидер гильдии.
 * Флаг приходит с GET /guilds/:id/settings.
 */
const canChangeLocalizationServer = ref(false);

/** Общий текст подсказки для поля «Локализация» и «Сервер». */
const LOCALIZATION_SERVER_INFO =
  'Локализацию и сервер можно изменить только пока в гильдии один участник — Лидер гильдии. ' +
  'При смене у персонажа-лидера локализация и сервер изменятся автоматически. ' +
  'Как только в гильдию вступит второй участник, эти поля будут заблокированы.';

const canEditGuildData = computed(() =>
  myPermissionSlugs.value.includes('redaktirovanie-dannyx-gildii')
);

/**
 * «Владелец» с точки зрения UI — это тот, кто реально может редактировать данные
 * гильдии: текущий лидер (он получает все гильдейские slug'и) или пользователь
 * с правом `redaktirovanie-dannyx-gildii`. Создатель гильдии (owner_id) после
 * смены лидера теряет эти права и видит страницу в режиме просмотра.
 */
const isOwner = computed(() => canEditGuildData.value);
const canEditCharter = computed(() =>
  myPermissionSlugs.value.includes('redaktirovanie-ustav-gildii')
);
const canEditAbout = computed(() =>
  myPermissionSlugs.value.includes('redaktirovanie-opisanie-gildii')
);
const canEditApplicationForm = computed(() =>
  myPermissionSlugs.value.includes('redaktirovat-formu-zaiavki-v-giliudiiu')
);

const tabs: { id: TabId; label: string }[] = [
  { id: 'settings', label: 'Настройки' },
  { id: 'about', label: 'О гильдии' },
  { id: 'charter', label: 'Устав' },
  { id: 'application', label: 'Форма заявки' },
];

/** Вкладки с учётом прав: «Настройки» при редактировании данных или возможности сменить лидера. */
const visibleTabs = computed(() => {
  return tabs.filter((t) => {
    if (t.id === 'settings') {
      return canEditGuildData.value || canChangeGuildLeader.value;
    }
    if (t.id === 'application') return canEditApplicationForm.value;
    return true;
  });
});

const activeTab = ref<TabId>('settings');

const name = ref('');
const selectedLocalizationId = ref<string>('');
const selectedServerId = ref<string>('');
const showRosterToAll = ref(false);
const aboutText = ref('');
const aboutPreviewMode = ref(false);
const charterText = ref('');
const charterPreviewMode = ref(false);

const logoFile = ref<File | null>(null);
const logoPreview = ref<string | null>(null);
const removeLogo = ref(false);
const dragOver = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const allTags = ref<Tag[]>([]);
const selectedTagIds = ref<number[]>([]);

/** Тип дополнительного поля формы заявки. */
type ApplicationFormFieldType = 'text' | 'textarea' | 'screenshot' | 'select' | 'multiselect';

const applicationFormFields = ref<GuildApplicationFormFieldDto[]>([]);

const applicationFieldSaving = ref(false);
const applicationFieldModalOpen = ref(false);
const applicationFieldEditIndex = ref<number | null>(null);
const applicationFieldName = ref('');
const applicationFieldType = ref<ApplicationFormFieldType>('text');
const applicationFieldRequired = ref(false);
/** Варианты выбора для полей select и multiselect. */
const applicationFieldOptions = ref<string[]>([]);

const leaveDialogOpen = ref(false);
const leaving = ref(false);
const leaveError = ref<string | null>(null);

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
  if (!isOwner.value) return;
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  target.value = '';
  if (file?.type.startsWith('image/')) {
    setLogoFile(file);
    await uploadLogo();
  }
}

async function onLogoDrop(e: DragEvent) {
  if (!isOwner.value) return;
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
  if (!isOwner.value) return;
  fileInputRef.value?.click();
}

async function loadGuild() {
  if (!guildId.value) return;
  loading.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.getGuildForSettings(guildId.value);
    myPermissionSlugs.value = guild.value.my_permission_slugs ?? [];
    canChangeGuildLeader.value = guild.value.can_change_guild_leader ?? false;
    canChangeLocalizationServer.value = guild.value.can_change_localization_server ?? false;
    selectedLeaderCharacterId.value =
      guild.value.leader_character_id != null ? String(guild.value.leader_character_id) : '';
    if (canChangeGuildLeader.value) {
      try {
        const roster = await guildsApi.getGuildRoster(guildId.value);
        leaderRosterMembers.value = [...roster].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
      } catch {
        leaderRosterMembers.value = [];
      }
    } else {
      leaderRosterMembers.value = [];
    }
    const visible = visibleTabs.value;
    if (!visible.some((t) => t.id === activeTab.value)) {
      activeTab.value = (visible[0]?.id ?? 'about') as TabId;
    }
    name.value = guild.value.name;
    selectedLocalizationId.value = String(guild.value.localization_id);
    selectedServerId.value = String(guild.value.server_id);
    showRosterToAll.value = guild.value.show_roster_to_all ?? false;
    aboutText.value = guild.value.about_text ?? '';
    charterText.value = guild.value.charter_text ?? '';
    selectedTagIds.value = (guild.value.tags ?? []).map((t) => t.id);
    applicationFormFields.value = guild.value.application_form_fields ?? [];
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

async function confirmLeaveGuild() {
  if (!guild.value || leaving.value) return;
  leaving.value = true;
  leaveError.value = null;
  try {
    await guildsApi.leaveGuild(guild.value.id);
    // После успешного выхода — на список гильдий
    router.push({ name: 'guilds' });
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    leaveError.value = err.message ?? 'Не удалось покинуть гильдию.';
  } finally {
    leaving.value = false;
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

watch(guildId, () => {
  loadGuild();
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

const tagDeleteDialogOpen = ref(false);
const tagToDelete = ref<Tag | null>(null);
const tagDeleteLoading = ref(false);

function canDeleteGuildTag(tag: Tag): boolean {
  if (!guild.value) return false;
  const u = authStore.user;
  if (u == null) return false;
  if (tag.used_by_guild_id != null && Number(tag.used_by_guild_id) === guild.value.id) {
    return myPermissionSlugs.value.includes('udaliat-teg-gildii');
  }
  if (!isOwner.value) return false;
  return tag.used_by_user_id != null && Number(tag.used_by_user_id) === u.id;
}

const canCreateGuildTag = computed(
  () => isOwner.value && myPermissionSlugs.value.includes('dobavliat-teg-gildii')
);

/** Комбобокс тегов: владелец или права на создание/удаление гильдейских тегов. */
const canOpenGuildTagPicker = computed(
  () =>
    isOwner.value ||
    myPermissionSlugs.value.includes('dobavliat-teg-gildii') ||
    myPermissionSlugs.value.includes('udaliat-teg-gildii')
);

function openTagDeleteConfirm(tag: Tag) {
  tagToDelete.value = tag;
  tagDeleteDialogOpen.value = true;
}

async function confirmDeleteTagForever() {
  const t = tagToDelete.value;
  if (!t || tagDeleteLoading.value || !guild.value) return;
  tagDeleteLoading.value = true;
  try {
    const isThisGuildTag =
      t.used_by_guild_id != null && Number(t.used_by_guild_id) === guild.value.id;
    if (isThisGuildTag) {
      await tagsApi.deleteGuildTag(guildId.value, t.id);
    } else {
      await tagsApi.deleteTag(t.id);
    }
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== t.id);
    try {
      allTags.value = await tagsApi.getTags(false, guildId.value);
    } catch {
      /* ignore */
    }
    tagDeleteDialogOpen.value = false;
    tagToDelete.value = null;
  } finally {
    tagDeleteLoading.value = false;
  }
}

/** В текущих значениях формы действительно меняется лидер по сравнению с гильдией. */
const isLeaderChanging = computed(() => {
  if (!guild.value || !canChangeGuildLeader.value) return false;
  if (!selectedLeaderCharacterId.value) return false;
  const current = guild.value.leader_character_id ?? null;
  return Number(selectedLeaderCharacterId.value) !== Number(current);
});

/** Имя выбранного кандидата в лидеры (для текста подтверждения). */
const selectedNewLeaderName = computed(() => {
  const id = Number(selectedLeaderCharacterId.value);
  return leaderRosterMembers.value.find((m) => m.character_id === id)?.name ?? '';
});

async function saveSettings() {
  // При смене лидера — сначала просим подтверждение, действие необратимо
  // (текущий лидер получает роль «Новичок», права на настройки гильдии теряются).
  if (isLeaderChanging.value) {
    leaderChangeDialogOpen.value = true;
    return;
  }
  await performSaveSettings();
}

async function confirmLeaderChange() {
  leaderChangeDialogOpen.value = false;
  await performSaveSettings();
}

async function performSaveSettings() {
  if (!guild.value) return;
  saving.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    const payload: Parameters<typeof guildsApi.updateGuild>[1] = {
      name: name.value.trim(),
      show_roster_to_all: showRosterToAll.value,
      tag_ids: selectedTagIds.value,
    };
    // Локализация/сервер отправляются только когда их разрешено менять
    // (ровно один участник — Лидер гильдии).
    if (canChangeLocalizationServer.value) {
      payload.localization_id = Number(selectedLocalizationId.value);
      payload.server_id = Number(selectedServerId.value);
    }
    if (canChangeGuildLeader.value && selectedLeaderCharacterId.value) {
      payload.leader_character_id = Number(selectedLeaderCharacterId.value);
    }
    guild.value = await guildsApi.updateGuild(guild.value.id, payload);
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

const applicationShortUrl = computed(() => {
  if (typeof window === 'undefined' || !guild.value) return '';
  return `${window.location.origin}/a${guild.value.id}`;
});
const guildPageShortUrl = computed(() => {
  if (typeof window === 'undefined' || !guild.value) return '';
  return `${window.location.origin}/g${guild.value.id}`;
});

const APPLICATION_FIELD_TYPE_OPTIONS: { value: ApplicationFormFieldType; label: string }[] = [
  { value: 'text', label: 'Текст' },
  { value: 'textarea', label: 'Большой текст' },
  { value: 'screenshot', label: 'Скриншот (ссылка на скриншот)' },
  { value: 'select', label: 'Выбор одного варианта (select)' },
  { value: 'multiselect', label: 'Выбор нескольких вариантов (multiselect)' },
];

const isSelectOrMultiselect = (t: ApplicationFormFieldType) => t === 'select' || t === 'multiselect';

function addApplicationFieldOption() {
  applicationFieldOptions.value = [...applicationFieldOptions.value, ''];
}

function removeApplicationFieldOption(index: number) {
  applicationFieldOptions.value = applicationFieldOptions.value.filter((_, i) => i !== index);
}

function setApplicationFieldOptionValue(index: number, value: string) {
  const next = [...applicationFieldOptions.value];
  next[index] = value;
  applicationFieldOptions.value = next;
}

// При переключении на select/multiselect показывать хотя бы одно поле варианта
watch(applicationFieldType, (t) => {
  if (isSelectOrMultiselect(t) && applicationFieldOptions.value.length === 0) {
    applicationFieldOptions.value = [''];
  }
});

function openAddApplicationFieldModal() {
  applicationFieldEditIndex.value = null;
  applicationFieldName.value = '';
  applicationFieldType.value = 'text';
  applicationFieldRequired.value = false;
  applicationFieldOptions.value = [];
  applicationFieldModalOpen.value = true;
}

function openEditApplicationFieldModal(index: number) {
  const field = applicationFormFields.value[index];
  if (!field) return;
  applicationFieldEditIndex.value = index;
  applicationFieldName.value = field.name;
  applicationFieldType.value = field.type as ApplicationFormFieldType;
  applicationFieldRequired.value = field.required;
  applicationFieldOptions.value = field.options?.length ? [...field.options] : [];
  applicationFieldModalOpen.value = true;
}

function closeApplicationFieldModal() {
  applicationFieldModalOpen.value = false;
  applicationFieldEditIndex.value = null;
}

async function saveApplicationFieldModal() {
  const name = applicationFieldName.value.trim();
  if (!guild.value || !name) return;
  const type = applicationFieldType.value;
  if (isSelectOrMultiselect(type)) {
    const opts = applicationFieldOptions.value.map((o) => o.trim()).filter(Boolean);
    if (opts.length === 0) {
      error.value = 'Добавьте хотя бы один вариант выбора для полей «Выбор» и «Мультивыбор».';
      return;
    }
  }
  applicationFieldSaving.value = true;
  error.value = null;
  try {
    const options = isSelectOrMultiselect(type)
      ? applicationFieldOptions.value.map((o) => o.trim()).filter(Boolean)
      : undefined;
    const idx = applicationFieldEditIndex.value;
    if (idx !== null) {
      const field = applicationFormFields.value[idx];
      if (!field) return;
      const updated = await guildsApi.updateApplicationFormField(guild.value.id, field.id, {
        name,
        type,
        required: applicationFieldRequired.value,
        options,
      });
      applicationFormFields.value = applicationFormFields.value.map((f, i) => (i === idx ? updated : f));
    } else {
      const payload: CreateGuildApplicationFormFieldPayload = {
        name,
        type,
        required: applicationFieldRequired.value,
        options,
      };
      const created = await guildsApi.createApplicationFormField(guild.value.id, payload);
      applicationFormFields.value = [...applicationFormFields.value, created];
    }
    closeApplicationFieldModal();
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Не удалось сохранить поле';
  } finally {
    applicationFieldSaving.value = false;
  }
}

async function deleteApplicationField(index: number) {
  if (!guild.value) return;
  const field = applicationFormFields.value[index];
  if (!field) return;
  applicationFieldSaving.value = true;
  error.value = null;
  try {
    await guildsApi.deleteApplicationFormField(guild.value.id, field.id);
    applicationFormFields.value = applicationFormFields.value.filter((_, i) => i !== index);
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Не удалось удалить поле';
  } finally {
    applicationFieldSaving.value = false;
  }
}

const togglingRecruiting = ref(false);
const isRecruiting = computed(() => guild.value?.is_recruiting ?? false);

async function toggleRecruiting() {
  if (!guild.value) return;
  togglingRecruiting.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.updateGuild(guild.value.id, {
      is_recruiting: !guild.value.is_recruiting,
    });
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Не удалось изменить набор в гильдию';
  } finally {
    togglingRecruiting.value = false;
  }
}

onMounted(async () => {
  loadGames();
  await loadGuild();
  try {
    allTags.value = await tagsApi.getTags(false, guildId.value);
  } catch {
    allTags.value = [];
  }
});
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
            <!-- Логотип: с правом редактирования — обводка, кнопка удалить, затемнение при наведении -->
            <div
              v-if="canEditGuildData"
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
                    :disabled="saving || !isOwner"
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
            <!-- Логотип: только просмотр — без обводки, кнопок и затемнения -->
            <div
              v-else
              class="relative flex h-[290px] w-full max-w-[290px] shrink-0 flex-col items-center justify-center overflow-hidden rounded-lg bg-muted/20"
            >
              <img
                v-if="logoDisplayUrl"
                :src="logoDisplayUrl"
                alt="Логотип гильдии"
                class="h-full w-full object-cover"
              />
              <span v-else class="text-sm text-muted-foreground">Нет логотипа</span>
            </div>
            <div class="mt-3 flex w-full max-w-[290px] flex-col items-center gap-3 text-center text-sm">
              <div class="font-medium text-foreground">
                Лидер: {{ guild.leader?.name ?? '—' }}
              </div>
              <!-- Дублирование выбранных тегов (без подписи; выбор — во вкладке «Настройки») -->
              <div v-if="selectedTagIds.length" class="flex w-full flex-wrap justify-center gap-2 md:justify-start">
                <Badge
                  v-for="tag in allTags.filter((t) => selectedTagIds.includes(t.id))"
                  :key="tag.id"
                  variant="outline"
                >
                  {{ tag.name }}
                </Badge>
              </div>
              <div class="text-muted-foreground">
                Участников: {{ guild.members_count ?? 0 }}
              </div>
              <!-- Сменить лидера — под числом участников -->
              <div
                v-if="canChangeGuildLeader && leaderRosterMembers.length"
                class="w-full space-y-1.5 text-left"
              >
                <Label class="text-xs text-muted-foreground">Сменить лидера</Label>
                <SelectRoot v-model="selectedLeaderCharacterId">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Участник гильдии" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="m in leaderRosterMembers"
                      :key="m.character_id"
                      :value="String(m.character_id)"
                    >
                      {{ m.name }}
                    </SelectItem>
                  </SelectContent>
                </SelectRoot>
                <p class="text-xs text-muted-foreground">
                  Только участники состава. Сохраните изменения во вкладке «Настройки» справа.
                </p>
                <p v-if="fieldErrors.leader_character_id" class="text-xs text-destructive">
                  {{ fieldErrors.leader_character_id }}
                </p>
              </div>
              <div
                v-if="!canChangeGuildLeader"
                class="mt-0"
              >
                <Button
                  variant="destructive"
                  size="sm"
                  :disabled="leaving"
                  @click="leaveDialogOpen = true"
                >
                  Покинуть гильдию
                </Button>
                <p v-if="leaveError" class="mt-1 text-xs text-destructive">{{ leaveError }}</p>
              </div>
            </div>
          </div>

          <!-- Правая колонка: табы и контент -->
          <div class="min-w-0 flex-1 order-2 md:order-2">
            <div class="mb-4 flex flex-wrap gap-1 border-b">
          <button
            v-for="t in visibleTabs"
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
            <p v-if="!isOwner && !canChangeGuildLeader" class="text-sm text-muted-foreground">
              Редактировать настройки может только лидер гильдии или участник с соответствующим правом. Вы можете просматривать информацию.
            </p>
            <p v-else-if="!isOwner && canChangeGuildLeader" class="text-sm text-muted-foreground">
              Вы можете сменить лидера гильдии (слева, под числом участников) и нажать «Сохранить настройки».
            </p>
          </CardHeader>
          <CardContent class="space-y-6">
            <div class="space-y-2">
              <Label for="settings-name">Название гильдии *</Label>
              <Input id="settings-name" v-model="name" :disabled="!isOwner" />
              <p v-if="fieldErrors.name" class="text-sm text-destructive">{{ fieldErrors.name }}</p>
            </div>

            <div class="space-y-2">
              <div class="flex items-center gap-1.5">
                <Label>Локализация *</Label>
                <Tooltip :content="LOCALIZATION_SERVER_INFO" side="top">
                  <button
                    type="button"
                    aria-label="Подсказка: когда можно менять локализацию"
                    class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10" />
                      <path d="M12 16v-4" />
                      <path d="M12 8h.01" />
                    </svg>
                  </button>
                </Tooltip>
              </div>
              <SelectRoot
                v-model="selectedLocalizationId"
                :disabled="!isOwner || !canChangeLocalizationServer || !availableLocalizations.length"
              >
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
              <div class="flex items-center gap-1.5">
                <Label>Сервер *</Label>
                <Tooltip :content="LOCALIZATION_SERVER_INFO" side="top">
                  <button
                    type="button"
                    aria-label="Подсказка: когда можно менять сервер"
                    class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10" />
                      <path d="M12 16v-4" />
                      <path d="M12 8h.01" />
                    </svg>
                  </button>
                </Tooltip>
              </div>
              <SelectRoot
                v-model="selectedServerId"
                :disabled="!isOwner || !canChangeLocalizationServer || !servers.length"
              >
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
                :disabled="!isOwner"
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
                <template
                  v-for="tag in allTags.filter((t) => selectedTagIds.includes(t.id))"
                  :key="tag.id"
                >
                  <Badge v-if="!canEditGuildData" variant="outline">
                    {{ tag.name }}
                  </Badge>
                  <Badge
                    v-else
                    variant="outline"
                    class="max-w-full gap-0.5 py-0 pl-2 pr-0.5"
                  >
                    <span class="min-w-0 truncate">{{ tag.name }}</span>
                    <button
                      type="button"
                      class="inline-flex shrink-0 rounded-sm p-0.5 text-muted-foreground outline-none hover:bg-destructive/15 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring"
                      :title="`Убрать тег «${tag.name}»`"
                      :aria-label="`Убрать тег «${tag.name}»`"
                      @click.stop.prevent="toggleTag(tag.id)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                      </svg>
                    </button>
                  </Badge>
                </template>
              </div>
              <TagAddCombobox
                v-model:all-tags="allTags"
                v-model:selected-tag-ids="selectedTagIds"
                input-id="tag-select"
                :disabled="!canOpenGuildTagPicker"
                :allow-create-tag="canCreateGuildTag"
                :tag-create-guild-id="guildId"
                :can-delete-tag="canDeleteGuildTag"
                @delete-tag="openTagDeleteConfirm"
              />
            </div>

            <Button :disabled="saving || (!isOwner && !canChangeGuildLeader)" @click="saveSettings">
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
            <template v-if="canEditAbout">
              <div class="flex flex-wrap items-center gap-2 border-b border-border pb-2">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  :class="{ 'bg-muted': !aboutPreviewMode }"
                  @click="aboutPreviewMode = false"
                >
                  Редактирование
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  :class="{ 'bg-muted': aboutPreviewMode }"
                  @click="aboutPreviewMode = true"
                >
                  Предпросмотр
                </Button>
              </div>
              <div v-show="!aboutPreviewMode" class="space-y-2">
                <Label for="about-text">Текст «О гильдии»</Label>
                <RichTextEditor
                  id="about-text"
                  v-model="aboutText"
                  placeholder="Расскажите о гильдии, целях и правилах…"
                  :disabled="saving || !isOwner"
                />
              </div>
              <div
                v-show="aboutPreviewMode"
                class="min-h-[200px] rounded-md border border-input bg-muted/30 px-3 py-3 text-sm"
              >
                <div
                  v-if="aboutText"
                  class="prose prose-sm max-w-none text-muted-foreground dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:decoration-blue-600/40 [&_a]:underline-offset-2 hover:[&_a]:text-blue-700 dark:[&_a]:text-blue-400 dark:hover:[&_a]:text-blue-300 dark:[&_a]:decoration-blue-400/50 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-foreground [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_ol]:space-y-1 [&_li]:my-0.5"
                  v-html="aboutText"
                />
                <p v-else class="text-muted-foreground">Нет текста. Переключитесь в режим редактирования и добавьте описание.</p>
              </div>
              <div v-show="!aboutPreviewMode" class="flex flex-wrap gap-2 pt-2">
                <Button :disabled="saving || !isOwner" @click="saveAbout">
                  {{ saving ? 'Сохранение…' : 'Сохранить' }}
                </Button>
              </div>
            </template>
            <template v-else>
              <div
                v-if="guild?.about_text"
                class="prose prose-sm max-w-none text-muted-foreground dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:decoration-blue-600/40 [&_a]:underline-offset-2 hover:[&_a]:text-blue-700 dark:[&_a]:text-blue-400 dark:hover:[&_a]:text-blue-300 dark:[&_a]:decoration-blue-400/50 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-foreground [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_ol]:space-y-1 [&_li]:my-0.5"
                v-html="guild.about_text"
              ></div>
              <p v-else class="text-sm text-muted-foreground">—</p>
            </template>
          </CardContent>
        </Card>

        <!-- Вкладка: Устав -->
        <Card v-show="activeTab === 'charter'" class="mb-6">
          <CardHeader>
            <CardTitle>Устав</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <template v-if="canEditCharter">
              <div class="flex flex-wrap items-center gap-2 border-b border-border pb-2">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  :class="{ 'bg-muted': !charterPreviewMode }"
                  @click="charterPreviewMode = false"
                >
                  Редактирование
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  :class="{ 'bg-muted': charterPreviewMode }"
                  @click="charterPreviewMode = true"
                >
                  Предпросмотр
                </Button>
              </div>
              <div v-show="!charterPreviewMode" class="space-y-2">
                <Label for="charter-text">Текст устава</Label>
                <textarea
                  id="charter-text"
                  v-model="charterText"
                  rows="12"
                  class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  placeholder="Устав гильдии…"
                />
              </div>
              <div
                v-show="charterPreviewMode"
                class="min-h-[200px] rounded-md border border-input bg-muted/30 px-3 py-3 text-sm"
              >
                <p
                  v-if="charterText"
                  class="whitespace-pre-wrap text-muted-foreground"
                >
                  {{ charterText }}
                </p>
                <p v-else class="text-muted-foreground">
                  Нет текста. Переключитесь в режим редактирования и добавьте устав.
                </p>
              </div>
              <div v-show="!charterPreviewMode" class="flex flex-wrap gap-2 pt-2">
                <Button :disabled="saving || !isOwner" @click="saveCharter">
                  {{ saving ? 'Сохранение…' : 'Сохранить' }}
                </Button>
              </div>
            </template>
            <p v-else class="whitespace-pre-wrap text-sm text-muted-foreground">
              {{ guild?.charter_text || '—' }}
            </p>
          </CardContent>
        </Card>

        <!-- Вкладка: Форма заявки -->
        <Card v-show="activeTab === 'application'" class="mb-6">
          <CardHeader>
            <CardTitle>Форма заявки</CardTitle>
          </CardHeader>
          <CardContent class="space-y-6">
            <p class="text-sm text-muted-foreground">
              Тут вы можете настроить форму для подачи заявки на вступление в гильдию. Для подачи заявки вам необходимо просто дать ссылку на форму. Заявку может подать как зарегистрированный на сайте пользователь, так и сторонний. При одобрении заявки, пользователь будет автоматически зарегистрирован на сайте. Ссылка на форму заявки. Короткая ссылка на форму заявки
              <a :href="applicationShortUrl" target="_blank" rel="noopener noreferrer" class="text-primary underline underline-offset-2 hover:no-underline">{{ applicationShortUrl }}</a>. Короткая ссылка на страницу гильдии
              <a :href="guildPageShortUrl" target="_blank" rel="noopener noreferrer" class="text-primary underline underline-offset-2 hover:no-underline">{{ guildPageShortUrl }}</a>.
            </p>

            <div class="border-b border-border pb-2">
              <h3 class="text-sm font-medium text-foreground underline decoration-border underline-offset-2">
                Обязательные поля для незарегистрированного пользователя
              </h3>
            </div>
            <div class="grid gap-3 gap-x-4 text-sm sm:grid-cols-2">
              <div class="space-y-1.5">
                <Label class="text-muted-foreground">Email</Label>
                <Input placeholder="Email" disabled class="bg-muted/50" />
              </div>
              <div class="space-y-1.5">
                <Label class="text-muted-foreground">Имя основного персонажа в игре</Label>
                <Input placeholder="Имя основного персонажа в игре" disabled class="bg-muted/50" />
              </div>
              <div class="space-y-1.5">
                <Label class="text-muted-foreground">Класс основного персонажа в игре</Label>
                <Input placeholder="Класс основного персонажа в игре" disabled class="bg-muted/50" />
              </div>
              <div class="space-y-1.5">
                <Label class="text-muted-foreground">Пароль</Label>
                <Input type="password" placeholder="Пароль" disabled class="bg-muted/50" />
              </div>
              <div class="space-y-1.5">
                <Label class="text-muted-foreground">Подтверждение пароля</Label>
                <Input type="password" placeholder="Подтверждение пароля" disabled class="bg-muted/50" />
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
              <h3 class="text-sm font-medium text-foreground underline decoration-border underline-offset-2">
                Дополнительные поля
              </h3>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                :disabled="applicationFieldSaving"
                @click="openAddApplicationFieldModal"
              >
                <span class="mr-1.5 text-base leading-none">+</span>
                Добавить дополнительное поле в форму заявки
              </Button>
            </div>
            <ul v-if="applicationFormFields.length" class="space-y-2">
              <li
                v-for="(field, index) in applicationFormFields"
                :key="field.id"
                class="flex items-center justify-between gap-2 rounded-md border border-border bg-muted/20 px-3 py-2 text-sm"
              >
                <div class="min-w-0 flex-1">
                  <span class="font-medium text-foreground">{{ field.name }}</span>
                  <span v-if="isSelectOrMultiselect(field.type as ApplicationFormFieldType) && field.options?.length" class="ml-2 text-xs text-muted-foreground">
                    {{ field.type === 'select' ? 'Выбор' : 'Мультивыбор' }} ({{ field.options.length }})
                  </span>
                </div>
                <div class="flex shrink-0 gap-1">
                  <button
                    type="button"
                    class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
                    aria-label="Редактировать поле"
                    :disabled="applicationFieldSaving"
                    @click="openEditApplicationFieldModal(index)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17 3a2.85 2.83 0 1 1 4 4L7 17l-4 1 1-4Z" />
                      <path d="m15 5 4 4" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-50"
                    aria-label="Удалить поле"
                    :disabled="applicationFieldSaving"
                    @click="deleteApplicationField(index)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18" />
                      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                      <line x1="10" x2="10" y1="11" y2="17" />
                      <line x1="14" x2="14" y1="11" y2="17" />
                    </svg>
                  </button>
                </div>
              </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
              Дополнительных полей пока нет. Нажмите кнопку выше, чтобы добавить.
            </p>

            <Button
              type="button"
              :variant="isRecruiting ? 'destructive' : 'success'"
              class="mt-4"
              :disabled="togglingRecruiting"
              @click="toggleRecruiting"
            >
              {{ togglingRecruiting ? 'Сохранение…' : isRecruiting ? 'Закрыть набор в гильдию' : 'Открыть набор в гильдию' }}
            </Button>
          </CardContent>
        </Card>

        <!-- Модалка: добавить/редактировать дополнительное поле формы заявки -->
        <DialogRoot v-model:open="applicationFieldModalOpen" @update:open="(v: boolean) => { if (!v) applicationFieldEditIndex.value = null; }">
          <ClientOnly>
          <DialogPortal>
            <DialogOverlay
              class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <DialogContent
              class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
              :aria-describedby="undefined"
            >
              <DialogTitle class="text-lg font-semibold">
                {{ applicationFieldEditIndex !== null ? 'Редактировать поле' : 'Добавить дополнительное поле' }}
              </DialogTitle>
              <DialogDescription class="sr-only">
                Название поля, тип поля и обязательность заполнения.
              </DialogDescription>
              <div class="space-y-4 pt-2">
                <div class="space-y-2">
                  <Label for="application-field-name">Название поля *</Label>
                  <Input
                    id="application-field-name"
                    v-model="applicationFieldName"
                    placeholder="Например: О себе"
                    :disabled="applicationFieldSaving"
                  />
                </div>
                <div class="space-y-2">
                  <Label for="application-field-type">Тип поля *</Label>
                  <SelectRoot v-model="applicationFieldType" :disabled="applicationFieldSaving">
                    <SelectTrigger id="application-field-type" class="w-full">
                      <SelectValue placeholder="Выберите тип" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="opt in APPLICATION_FIELD_TYPE_OPTIONS"
                        :key="opt.value"
                        :value="opt.value"
                      >
                        {{ opt.label }}
                      </SelectItem>
                    </SelectContent>
                  </SelectRoot>
                </div>
                <div class="flex items-center gap-2">
                  <input
                    id="application-field-required"
                    v-model="applicationFieldRequired"
                    type="checkbox"
                    class="h-4 w-4 rounded border-input"
                  />
                  <Label for="application-field-required" class="cursor-pointer font-normal">
                    Обязательное для заполнения
                  </Label>
                </div>
                <template v-if="isSelectOrMultiselect(applicationFieldType)">
                  <div class="space-y-2">
                    <Label>Варианты выбора *</Label>
                    <p class="text-xs text-muted-foreground">
                      Добавьте позиции, которые можно будет выбрать при подаче заявки.
                    </p>
                    <div class="space-y-2">
                      <div
                        v-for="(opt, optIndex) in applicationFieldOptions"
                        :key="optIndex"
                        class="flex items-center gap-2"
                      >
                        <Input
                          :model-value="opt"
                          placeholder="Текст варианта"
                          class="flex-1"
                          :disabled="applicationFieldSaving"
                          @update:model-value="setApplicationFieldOptionValue(optIndex, $event)"
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          class="h-9 w-9 shrink-0 text-destructive hover:text-destructive"
                          aria-label="Удалить вариант"
                          :disabled="applicationFieldSaving"
                          @click="removeApplicationFieldOption(optIndex)"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" /><path d="m6 6 12 12" />
                          </svg>
                        </Button>
                      </div>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      :disabled="applicationFieldSaving"
                      @click="addApplicationFieldOption"
                    >
                      + Добавить вариант
                    </Button>
                  </div>
                </template>
              </div>
              <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" :disabled="applicationFieldSaving" @click="closeApplicationFieldModal">
                  Отмена
                </Button>
                <Button
                  type="button"
                  :disabled="!applicationFieldName.trim() || applicationFieldSaving || (isSelectOrMultiselect(applicationFieldType) && !applicationFieldOptions.some((o) => o.trim()))"
                  @click="saveApplicationFieldModal"
                >
                  {{ applicationFieldSaving ? 'Сохранение…' : applicationFieldEditIndex !== null ? 'Сохранить' : 'Добавить' }}
                </Button>
              </div>
            </DialogContent>
          </DialogPortal>
          </ClientOnly>
        </DialogRoot>
          </div>
        </div>
      </template>
    </div>
    <ConfirmDialog
      :open="tagDeleteDialogOpen"
      :title="tagToDelete ? `Удалить тег «${tagToDelete.name}»?` : 'Удалить тег?'"
      description="Тег исчезнет из всех персонажей и гильдий. Это действие нельзя отменить."
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="tagDeleteLoading"
      confirm-variant="destructive"
      @update:open="(v) => { tagDeleteDialogOpen = v; }"
      @confirm="confirmDeleteTagForever"
    />
    <ConfirmDialog
      :open="leaveDialogOpen"
      title="Покинуть гильдию?"
      :description="'Вы перестанете быть участником этой гильдии. Доступ к настройкам и разделам гильдии будет закрыт.'"
      confirm-label="Покинуть"
      cancel-label="Отмена"
      :loading="leaving"
      confirm-variant="destructive"
      @update:open="(v) => { leaveDialogOpen = v; }"
      @confirm="confirmLeaveGuild"
    />
    <ConfirmDialog
      :open="leaderChangeDialogOpen"
      title="Сменить лидера гильдии?"
      :description="
        selectedNewLeaderName
          ? `Лидером гильдии станет «${selectedNewLeaderName}». Вы получите роль «Новичок» и потеряете доступ к настройкам гильдии. Действие нельзя отменить — новому лидеру придётся передать роль обратно.`
          : 'Вы получите роль «Новичок» и потеряете доступ к настройкам гильдии. Действие нельзя отменить — новому лидеру придётся передать роль обратно.'
      "
      confirm-label="Сменить лидера"
      cancel-label="Отмена"
      :loading="saving"
      confirm-variant="destructive"
      @update:open="(v) => { leaderChangeDialogOpen = v; }"
      @confirm="confirmLeaderChange"
    />
  </div>
</template>
