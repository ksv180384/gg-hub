import { computed, onMounted, ref, watch, type Ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import {
  guildsApi,
  type CreateGuildApplicationFormFieldPayload,
  type Guild,
  type GuildApplicationFormFieldDto,
  type GuildRosterMember,
} from '@/shared/api/guildsApi';
import { gamesApi, type Game, type Server } from '@/shared/api/gamesApi';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';

export const ACCEPT_IMAGES = 'image/jpeg,image/png,image/jpg,image/gif,image/webp';

export type TabId = 'settings' | 'about' | 'charter' | 'application' | 'discord';
export const DEFAULT_TABS: { id: TabId; label: string }[] = [
  { id: 'settings', label: 'Настройки' },
  { id: 'about', label: 'О гильдии' },
  { id: 'charter', label: 'Устав' },
  { id: 'application', label: 'Форма заявки' },
  { id: 'discord', label: 'Discord' },
];

/**
 * Тип одного из 7 ключей-оповещений Discord.
 */
export type DiscordNotificationKey =
  | 'discord_notify_application_new'
  | 'discord_notify_member_joined'
  | 'discord_notify_member_left'
  | 'discord_notify_event_starting'
  | 'discord_notify_poll_started'
  | 'discord_notify_role_changed'
  | 'discord_notify_post_published';

/**
 * Метки для чекбоксов оповещений Discord (для UI).
 */
export const DISCORD_NOTIFICATION_LABELS: { key: DiscordNotificationKey; label: string }[] = [
  { key: 'discord_notify_application_new', label: 'Новая заявка вступления в гильдию' },
  { key: 'discord_notify_member_joined', label: 'Пользователь вступил в гильдию' },
  { key: 'discord_notify_member_left', label: 'Пользователь покинул гильдию' },
  { key: 'discord_notify_event_starting', label: 'Начало гильдейского события (за 10 мин)' },
  { key: 'discord_notify_poll_started', label: 'Запуск нового голосования' },
  { key: 'discord_notify_role_changed', label: 'Смена роли пользователю' },
  { key: 'discord_notify_post_published', label: 'Публикация нового поста гильдии' },
];

/**
 * Регулярка для URL Discord-вебхука. Дублируется на бэкенде в UpdateGuildRequest:
 * https://discord.com|discordapp.com|ptb.discord.com|canary.discord.com /api/webhooks/<id>/<token>
 */
const DISCORD_WEBHOOK_URL_REGEX =
  /^https:\/\/(discord\.com|discordapp\.com|ptb\.discord\.com|canary\.discord\.com)\/api\/webhooks\/\d+\/[A-Za-z0-9_-]+$/;

export function isValidDiscordWebhookUrl(url: string): boolean {
  return DISCORD_WEBHOOK_URL_REGEX.test(url);
}

/** Тип дополнительного поля формы заявки. */
export type ApplicationFormFieldType = 'text' | 'textarea' | 'screenshot' | 'select' | 'multiselect';

export const APPLICATION_FIELD_TYPE_OPTIONS: { value: ApplicationFormFieldType; label: string }[] = [
  { value: 'text', label: 'Текст' },
  { value: 'textarea', label: 'Большой текст' },
  { value: 'screenshot', label: 'Скриншот (ссылка на скриншот)' },
  { value: 'select', label: 'Выбор одного варианта (select)' },
  { value: 'multiselect', label: 'Выбор нескольких вариантов (multiselect)' },
];

export const LOCALIZATION_SERVER_INFO =
  'Локализацию и сервер можно изменить только пока в гильдии один участник — Лидер гильдии. ' +
  'При смене у персонажа-лидера локализация и сервер изменятся автоматически. ' +
  'Как только в гильдию вступит второй участник, эти поля будут заблокированы.';

const isSelectOrMultiselect = (t: ApplicationFormFieldType) => t === 'select' || t === 'multiselect';

function mergeGuildTagsIntoAllTags(guild: Ref<Guild | null>, allTags: Ref<Tag[]>) {
  const guildTags = guild.value?.tags ?? [];
  if (!guildTags.length) return;
  const known = new Set(allTags.value.map((t) => t.id));
  const extras: Tag[] = guildTags
    .filter((t) => !known.has(t.id))
    .map((t) => ({
      id: t.id,
      name: t.name,
      slug: '',
      is_hidden: false,
      used_by_user_id: null,
      used_by_guild_id: null,
      created_by_user_id: null,
      used_by: null,
      used_by_guild: null,
      created_by: null,
    })) as unknown as Tag[];
  if (extras.length) allTags.value = [...allTags.value, ...extras];
}

export function useGuildSettingsModel() {
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

  /** Теги на карточке гильдии (guild_tag): право «Изменять теги гильдии». */
  const canEditGuildTags = computed(() =>
    myPermissionSlugs.value.includes('izmeniat-tegi-gildii')
  );
  const canCreateGuildTag = computed(
    () => canEditGuildTags.value && myPermissionSlugs.value.includes('dobavliat-teg-gildii')
  );
  /** Редактирование привязки тегов к гильдии — только izmeniat-tegi-gildii. */
  const canOpenGuildTagPicker = computed(() => canEditGuildTags.value);

  const tabs = ref(DEFAULT_TABS);
  /** Вкладки с учётом прав: «Настройки» при редактировании данных или возможности сменить лидера. */
  const visibleTabs = computed(() =>
    tabs.value.filter((t) => {
      if (t.id === 'settings') return canEditGuildData.value || canChangeGuildLeader.value;
      if (t.id === 'application') return canEditApplicationForm.value;
      if (t.id === 'discord') return canEditGuildData.value;
      return true;
    })
  );
  const activeTab = ref<TabId>('settings');

  // form state
  const name = ref('');
  const selectedLocalizationId = ref<string>('');
  const selectedServerId = ref<string>('');
  const aboutText = ref('');
  const aboutPreviewMode = ref(false);
  const charterText = ref('');
  const charterPreviewMode = ref(false);
  const applicationFormDescription = ref('');

  // logo
  const logoFile = ref<File | null>(null);
  const logoPreview = ref<string | null>(null);
  const removeLogo = ref(false);
  const dragOver = ref(false);

  // tags
  const allTags = ref<Tag[]>([]);
  const selectedTagIds = ref<number[]>([]);
  const tagDeleteDialogOpen = ref(false);
  const tagToDelete = ref<Tag | null>(null);
  const tagDeleteLoading = ref(false);

  // application fields
  const applicationFormFields = ref<GuildApplicationFormFieldDto[]>([]);
  const applicationFieldSaving = ref(false);
  const applicationFieldModalOpen = ref(false);
  const applicationFieldEditIndex = ref<number | null>(null);
  const applicationFieldName = ref('');
  const applicationFieldType = ref<ApplicationFormFieldType>('text');
  const applicationFieldRequired = ref(false);
  const applicationFieldOptions = ref<string[]>([]);

  // leave guild
  const leaveDialogOpen = ref(false);
  const leaving = ref(false);
  const leaveError = ref<string | null>(null);

  // discord
  const discordWebhookUrl = ref('');
  const discordWebhookError = ref<string | null>(null);
  const discordSaving = ref(false);
  const discordNotifications = ref<Record<DiscordNotificationKey, boolean>>({
    discord_notify_application_new: false,
    discord_notify_member_joined: false,
    discord_notify_member_left: false,
    discord_notify_event_starting: false,
    discord_notify_poll_started: false,
    discord_notify_role_changed: false,
    discord_notify_post_published: false,
  });
  /** Какая галочка оповещения Discord сейчас сохраняется на сервер (остальные временно блокируются). */
  const discordNotifySavingKey = ref<DiscordNotificationKey | null>(null);

  function applyDiscordStateFromGuild(g: Guild) {
    discordWebhookUrl.value = g.discord_webhook_url ?? '';
    discordNotifications.value = {
      discord_notify_application_new: g.discord_notify_application_new ?? false,
      discord_notify_member_joined: g.discord_notify_member_joined ?? false,
      discord_notify_member_left: g.discord_notify_member_left ?? false,
      discord_notify_event_starting: g.discord_notify_event_starting ?? false,
      discord_notify_poll_started: g.discord_notify_poll_started ?? false,
      discord_notify_role_changed: g.discord_notify_role_changed ?? false,
      discord_notify_post_published: g.discord_notify_post_published ?? false,
    };
  }

  // games / servers
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

  const togglingRecruiting = ref(false);
  const isRecruiting = computed(() => guild.value?.is_recruiting ?? false);

  function setLogoFile(file: File | null) {
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value);
    logoFile.value = file ?? null;
    logoPreview.value = file ? URL.createObjectURL(file) : null;
    removeLogo.value = false;
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
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
  }

  function onLogoDragLeave() {
    dragOver.value = false;
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

  function toggleTag(tagId: number) {
    const idx = selectedTagIds.value.indexOf(tagId);
    if (idx >= 0) {
      selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
    } else {
      selectedTagIds.value = [...selectedTagIds.value, tagId];
    }
  }

  function canDeleteGuildTag(tag: Tag): boolean {
    if (!guild.value) return false;
    const u = authStore.user;
    if (u == null) return false;
    if (tag.used_by_guild_id != null && Number(tag.used_by_guild_id) === guild.value.id) {
      return canEditGuildTags.value && myPermissionSlugs.value.includes('udaliat-teg-gildii');
    }
    if (!isOwner.value) return false;
    return tag.used_by_user_id != null && Number(tag.used_by_user_id) === u.id;
  }

  function openTagDeleteConfirm(tag: Tag) {
    tagToDelete.value = tag;
    tagDeleteDialogOpen.value = true;
  }

  async function confirmDeleteTagForever() {
    const t = tagToDelete.value;
    if (!t || tagDeleteLoading.value || !guild.value) return;
    tagDeleteLoading.value = true;
    try {
      const isThisGuildTag = t.used_by_guild_id != null && Number(t.used_by_guild_id) === guild.value.id;
      if (isThisGuildTag) await tagsApi.deleteGuildTag(guildId.value, t.id);
      else await tagsApi.deleteTag(t.id);

      selectedTagIds.value = selectedTagIds.value.filter((id) => id !== t.id);
      try {
        allTags.value = await tagsApi.getTags(false, guildId.value);
      } catch {
        /* ignore */
      }
      mergeGuildTagsIntoAllTags(guild, allTags);
      tagDeleteDialogOpen.value = false;
      tagToDelete.value = null;
    } finally {
      tagDeleteLoading.value = false;
    }
  }

  async function loadGames() {
    try {
      games.value = await gamesApi.getGames();
    } catch {
      games.value = [];
    }
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
          const { members: rosterMembers } = await guildsApi.getGuildRoster(guildId.value);
          leaderRosterMembers.value = [...rosterMembers].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
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
      aboutText.value = guild.value.about_text ?? '';
      charterText.value = guild.value.charter_text ?? '';
      applicationFormDescription.value = guild.value.application_form_description ?? '';
      selectedTagIds.value = (guild.value.tags ?? []).map((t) => t.id);
      applicationFormFields.value = guild.value.application_form_fields ?? [];
      discordWebhookError.value = null;
      applyDiscordStateFromGuild(guild.value);
    } catch (e: unknown) {
      const err = e as { status?: number };
      if (err.status === 403 || err.status === 404) {
        router.replace('/guilds');
        return;
      }
      error.value = 'Не удалось загрузить гильдию';
    } finally {
      loading.value = false;
    }
  }

  watch(guildId, () => {
    loadGuild();
  });

  watch(selectedLocalizationId, (locId) => {
    const g = guild.value;
    if (!g || !locId) return;
    gamesApi
      .getServers(g.game_id, Number(locId))
      .then((list) => {
        servers.value = list;
      })
      .catch(() => {
        servers.value = [];
      });
  });

  watch(applicationFieldType, (t) => {
    if (isSelectOrMultiselect(t) && applicationFieldOptions.value.length === 0) {
      applicationFieldOptions.value = [''];
    }
  });

  async function performSaveSettings() {
    if (!guild.value) return;
    saving.value = true;
    error.value = null;
    fieldErrors.value = {};
    try {
      const payload: Parameters<typeof guildsApi.updateGuild>[1] = {
        name: name.value.trim(),
      };
      if (canEditGuildTags.value) payload.tag_ids = selectedTagIds.value;
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

  async function saveSettings() {
    // При смене лидера — сначала просим подтверждение, действие необратимо:
    // текущий лидер получает роль «Новичок» и теряет доступ к настройкам.
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

  async function saveApplicationFormDescription() {
    if (!guild.value) return;
    saving.value = true;
    error.value = null;
    try {
      guild.value = await guildsApi.updateGuild(guild.value.id, {
        application_form_description: applicationFormDescription.value.trim() || null,
      });
      applicationFormDescription.value = guild.value.application_form_description ?? '';
    } catch (e: unknown) {
      error.value = (e as Error).message ?? 'Не удалось сохранить описание формы';
    } finally {
      saving.value = false;
    }
  }

  /**
   * Сохранение настроек Discord-вебхука и галочек оповещений.
   * Перед отправкой проверяет URL по тому же regex, что и бэкенд (UpdateGuildRequest).
   * Пустой URL допустим — означает «удалить вебхук» и автоматически выключает все галочки.
   */
  async function saveDiscord() {
    if (!guild.value) return;
    discordWebhookError.value = null;

    const url = discordWebhookUrl.value.trim();
    if (url !== '' && !isValidDiscordWebhookUrl(url)) {
      discordWebhookError.value =
        'Укажите корректный URL Discord-вебхука вида https://discord.com/api/webhooks/<id>/<token>.';
      return;
    }

    discordSaving.value = true;
    error.value = null;
    try {
      const payload: Parameters<typeof guildsApi.updateGuild>[1] = {
        discord_webhook_url: url === '' ? null : url,
        ...discordNotifications.value,
      };
      // Если вебхук удалён — никаких оповещений отправлять некуда, выключаем флаги.
      if (url === '') {
        payload.discord_notify_application_new = false;
        payload.discord_notify_member_joined = false;
        payload.discord_notify_member_left = false;
        payload.discord_notify_event_starting = false;
        payload.discord_notify_poll_started = false;
        payload.discord_notify_role_changed = false;
        payload.discord_notify_post_published = false;
      }

      guild.value = await guildsApi.updateGuild(guild.value.id, payload);
      applyDiscordStateFromGuild(guild.value);
    } catch (e: unknown) {
      const err = e as Error & { errors?: Record<string, string[] | string> };
      if (err.errors?.discord_webhook_url) {
        const msg = err.errors.discord_webhook_url;
        discordWebhookError.value = Array.isArray(msg) ? (msg[0] ?? null) : String(msg);
      } else {
        error.value = err.message ?? 'Не удалось сохранить настройки Discord';
      }
    } finally {
      discordSaving.value = false;
    }
  }

  /** Сохраняет одну галочку оповещения Discord сразу после изменения. */
  async function saveDiscordNotification(key: DiscordNotificationKey, value: boolean) {
    if (!guild.value) return;
    if (discordWebhookUrl.value.trim() === '') return;

    const prev = discordNotifications.value[key];
    discordNotifications.value = { ...discordNotifications.value, [key]: value };
    discordNotifySavingKey.value = key;
    error.value = null;
    try {
      guild.value = await guildsApi.updateGuild(guild.value.id, { [key]: value });
      applyDiscordStateFromGuild(guild.value);
    } catch (e: unknown) {
      discordNotifications.value = { ...discordNotifications.value, [key]: prev };
      error.value = (e as Error).message ?? 'Не удалось сохранить настройку оповещения';
    } finally {
      discordNotifySavingKey.value = null;
    }
  }

  async function confirmLeaveGuild() {
    if (!guild.value || leaving.value) return;
    leaving.value = true;
    leaveError.value = null;
    try {
      await guildsApi.leaveGuild(guild.value.id);
      router.push({ name: 'guilds' });
    } catch (e: unknown) {
      const err = e as Error & { message?: string };
      leaveError.value = err.message ?? 'Не удалось покинуть гильдию.';
    } finally {
      leaving.value = false;
    }
  }

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

  async function saveApplicationFieldModal() {
    const trimmed = applicationFieldName.value.trim();
    if (!guild.value || !trimmed) return;

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
          name: trimmed,
          type,
          required: applicationFieldRequired.value,
          options,
        });
        applicationFormFields.value = applicationFormFields.value.map((f, i) => (i === idx ? updated : f));
      } else {
        const payload: CreateGuildApplicationFormFieldPayload = {
          name: trimmed,
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

  onMounted(async () => {
    loadGames();
    await loadGuild();
    try {
      allTags.value = await tagsApi.getTags(false, guildId.value);
    } catch {
      allTags.value = [];
    }
    mergeGuildTagsIntoAllTags(guild, allTags);
  });

  return {
    // constants/helpers
    ACCEPT_IMAGES,
    LOCALIZATION_SERVER_INFO,
    APPLICATION_FIELD_TYPE_OPTIONS,
    isSelectOrMultiselect,

    // routing
    guildId,

    // core state
    guild,
    loading,
    saving,
    error,
    fieldErrors,

    // permissions
    myPermissionSlugs,
    canEditGuildData,
    isOwner,
    canEditCharter,
    canEditAbout,
    canEditApplicationForm,
    canEditGuildTags,
    canCreateGuildTag,
    canOpenGuildTagPicker,
    canChangeGuildLeader,
    canChangeLocalizationServer,

    // tabs
    activeTab,
    visibleTabs,

    // form fields
    name,
    selectedLocalizationId,
    selectedServerId,
    aboutText,
    aboutPreviewMode,
    charterText,
    charterPreviewMode,
    applicationFormDescription,

    // logo
    dragOver,
    logoDisplayUrl,
    onLogoChange,
    onLogoDrop,
    onLogoDragOver,
    onLogoDragLeave,
    removeLogoAndSave,

    // tags
    allTags,
    selectedTagIds,
    toggleTag,
    canDeleteGuildTag,
    openTagDeleteConfirm,
    tagDeleteDialogOpen,
    tagToDelete,
    tagDeleteLoading,
    confirmDeleteTagForever,

    // leader
    leaderRosterMembers,
    selectedLeaderCharacterId,
    leaderChangeDialogOpen,
    selectedNewLeaderName,
    confirmLeaderChange,

    // games/servers
    games,
    servers,
    availableLocalizations,

    // application fields
    applicationFormFields,
    applicationFieldSaving,
    applicationFieldModalOpen,
    applicationFieldEditIndex,
    applicationFieldName,
    applicationFieldType,
    applicationFieldRequired,
    applicationFieldOptions,
    openAddApplicationFieldModal,
    openEditApplicationFieldModal,
    closeApplicationFieldModal,
    addApplicationFieldOption,
    removeApplicationFieldOption,
    setApplicationFieldOptionValue,
    saveApplicationFieldModal,
    deleteApplicationField,

    // urls
    applicationShortUrl,
    guildPageShortUrl,

    // actions
    saveSettings,
    saveAbout,
    saveCharter,
    saveApplicationFormDescription,

    // recruiting
    isRecruiting,
    togglingRecruiting,
    toggleRecruiting,

    // leave guild
    leaveDialogOpen,
    leaving,
    leaveError,
    confirmLeaveGuild,

    // discord
    discordWebhookUrl,
    discordWebhookError,
    discordSaving,
    discordNotifications,
    discordNotifySavingKey,
    saveDiscord,
    saveDiscordNotification,
  };
}

