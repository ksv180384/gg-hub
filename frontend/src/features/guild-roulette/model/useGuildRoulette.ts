import { computed, ref, shallowRef, unref, watch, type Ref } from 'vue';
import type { ApiError } from '@/shared/api/errors';
import { GUILD_PERMISSION_MANAGE_ROULETTE } from '@/shared/api/guildPermissionSlugs';
import {
  guildsApi,
  type Guild,
  type GuildRosterMember,
} from '@/shared/api/guildsApi';
import { parseParticipantNicknamesFromXlsxFile } from '@/shared/lib/eventHistoryParticipantsXlsxImport';
import {
  useGuildRouletteWheelSocket,
  type GuildRouletteSpinWheelExpose,
} from '@/shared/lib/useGuildRouletteWheelSocket';
import { useAuthStore } from '@/stores/auth';
import { createWheelExternalId } from './wheelEntryUtils';
import { getUserColorByIndex, type UserColorTheme } from './userColors';
import {
  WHEEL_EMPTY_PLACEHOLDER,
  WHEEL_SPIN_SEC_DEFAULT,
  WHEEL_SPIN_SEC_MAX,
  WHEEL_SPIN_SEC_MIN,
  type WheelEntry,
} from './types';

/**
 * Composable со всем состоянием страницы рулетки гильдии:
 * загрузка состава и прав, управление списком участников на колесе,
 * импорт ников из Excel, длительность спина и синхронизация через сокет.
 */
export function useGuildRoulette(guildId: Ref<number>) {
  const roster = ref<GuildRosterMember[]>([]);
  const loading = ref(true);
  const error = ref<string | null>(null);
  const guildRouletteAccessNotFound = ref(false);

  const searchQuery = ref('');

  /** Участники на колесе: из состава или только ник (из Excel / не из гильдии). */
  const wheelEntries = ref<WheelEntry[]>([]);

  const importWheelExcelLoading = ref(false);
  const importWheelExcelError = ref('');

  const externalWheelNickname = ref('');
  const externalWheelHintError = ref('');
  const dkpCoefficientDraftByCharacterId = ref<Record<number, string>>({});
  const dkpCoefficientErrorByCharacterId = ref<Record<number, string>>({});
  const rouletteDkpCoefficientByCharacterId = ref<Record<number, number>>({});
  const externalDkpCoefficientDraftById = ref<Record<string, string>>({});
  const externalDkpCoefficientErrorById = ref<Record<string, string>>({});
  const rouletteDkpCoefficientByExternalId = ref<Record<string, number>>({});

  const filteredRoster = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return roster.value;
    return roster.value.filter((m) => m.name.toLowerCase().includes(q));
  });

  const wheelOptions = computed(() =>
    wheelEntries.value.map((e) => {
      if (e.kind === 'guild') {
        return (
          roster.value.find((m) => m.character_id === e.character_id)?.name ??
          `Персонаж #${e.character_id}`
        );
      }
      return e.name;
    })
  );

  function isInWheel(characterId: number) {
    return wheelEntries.value.some(
      (x) => x.kind === 'guild' && x.character_id === characterId
    );
  }

  function findRosterMemberByNickname(raw: string): GuildRosterMember | undefined {
    const q = raw.trim().toLowerCase();
    if (!q) return undefined;
    return roster.value.find((m) => m.name.trim().toLowerCase() === q);
  }

  function addToWheel(member: GuildRosterMember) {
    if (isInWheel(member.character_id)) return;
    wheelEntries.value = [
      ...wheelEntries.value,
      { kind: 'guild', character_id: member.character_id },
    ];
  }

  /** Весь текущий состав гильдии уже на колесе (только персонажи из roster). */
  const allRosterAlreadyOnWheel = computed(() => {
    if (!roster.value.length) return true;
    return roster.value.every((m) => isInWheel(m.character_id));
  });

  function addAllRosterToWheel() {
    if (!roster.value.length) return;
    const existingGuildIds = new Set(
      wheelEntries.value.filter((e) => e.kind === 'guild').map((e) => e.character_id)
    );
    const additions: WheelEntry[] = [];
    for (const m of roster.value) {
      if (!existingGuildIds.has(m.character_id)) {
        existingGuildIds.add(m.character_id);
        additions.push({ kind: 'guild', character_id: m.character_id });
      }
    }
    if (!additions.length) return;
    wheelEntries.value = [...wheelEntries.value, ...additions];
  }

  function resetWheelEntries() {
    wheelEntries.value = [];
    externalWheelNickname.value = '';
    externalWheelHintError.value = '';
    importWheelExcelError.value = '';
    eliminationActive.value = false;
    clearEliminationNextSpinTimer();
  }

  function removeGuildFromWheel(characterId: number) {
    wheelEntries.value = wheelEntries.value.filter(
      (e) => !(e.kind === 'guild' && e.character_id === characterId)
    );
  }

  function removeExternalFromWheel(id: string) {
    wheelEntries.value = wheelEntries.value.filter(
      (e) => !(e.kind === 'external' && e.id === id)
    );
  }

  function removeWheelEntryAtIndex(index: number) {
    if (index < 0 || index >= wheelEntries.value.length) return;
    wheelEntries.value = wheelEntries.value.filter((_, i) => i !== index);
  }

  function getWheelEntryName(entry: WheelEntry | undefined): string | null {
    if (!entry) return null;
    if (entry.kind === 'external') return entry.name;
    return (
      roster.value.find((m) => m.character_id === entry.character_id)?.name ??
      `Персонаж #${entry.character_id}`
    );
  }

  function addExternalParticipantByNickname() {
    externalWheelHintError.value = '';
    const trimmed = externalWheelNickname.value.trim();
    if (!trimmed) return;
    if (findRosterMemberByNickname(trimmed)) {
      externalWheelHintError.value =
        'Этот ник есть в составе гильдии — добавьте участника кнопкой в списке ниже.';
      return;
    }
    if (
      wheelEntries.value.some(
        (e) =>
          e.kind === 'external' &&
          e.name.trim().toLowerCase() === trimmed.toLowerCase()
      )
    ) {
      externalWheelHintError.value = 'Уже на колесе.';
      return;
    }
    wheelEntries.value = [
      ...wheelEntries.value,
      { kind: 'external', id: createWheelExternalId(), name: trimmed },
    ];
    externalWheelNickname.value = '';
  }

  async function importWheelExcelFromFile(file: File) {
    importWheelExcelLoading.value = true;
    importWheelExcelError.value = '';
    try {
      const nicknames = await parseParticipantNicknamesFromXlsxFile(file);
      if (!nicknames.length) {
        importWheelExcelError.value = 'В файле нет ников в первом столбце.';
        return;
      }
      let added = 0;
      const next = [...wheelEntries.value];

      for (const nick of nicknames) {
        const member = findRosterMemberByNickname(nick);
        if (member) {
          if (
            !next.some(
              (e) => e.kind === 'guild' && e.character_id === member.character_id
            )
          ) {
            next.push({ kind: 'guild', character_id: member.character_id });
            added += 1;
          }
        } else {
          const trimmed = nick.trim();
          if (!trimmed) continue;
          if (
            !next.some(
              (e) =>
                e.kind === 'external' &&
                e.name.trim().toLowerCase() === trimmed.toLowerCase()
            )
          ) {
            next.push({ kind: 'external', id: createWheelExternalId(), name: trimmed });
            added += 1;
          }
        }
      }
      wheelEntries.value = next;
      if (!added) {
        importWheelExcelError.value = 'Все строки из файла уже есть на колесе.';
      }
    } catch (e: unknown) {
      importWheelExcelError.value =
        e instanceof Error ? e.message : 'Не удалось прочитать Excel-файл.';
    } finally {
      importWheelExcelLoading.value = false;
    }
  }

  function clearImportWheelExcelError() {
    importWheelExcelError.value = '';
  }

  async function loadRoster() {
    if (!guildId.value || Number.isNaN(guildId.value)) return;
    loading.value = true;
    error.value = null;
    try {
      roster.value = (await guildsApi.getGuildRoster(guildId.value)).members;
    } catch (e: unknown) {
      const err = e as ApiError;
      if (err.status === 403 || err.status === 404) {
        guildRouletteAccessNotFound.value = true;
        error.value = null;
      } else {
        error.value = err instanceof Error ? err.message : 'Ошибка загрузки состава';
      }
      roster.value = [];
    } finally {
      loading.value = false;
    }
  }

  const guildForPerms = ref<Guild | null>(null);

  async function loadGuildSettingsForRoulette() {
    guildForPerms.value = null;
    if (!guildId.value || Number.isNaN(guildId.value)) return;
    try {
      guildForPerms.value = await guildsApi.getGuildForSettings(guildId.value);
    } catch (e: unknown) {
      const err = e as ApiError;
      if (err.status === 403 || err.status === 404) {
        guildRouletteAccessNotFound.value = true;
      }
      guildForPerms.value = null;
    }
  }

  const canManageRoulette = computed(
    () =>
      !!guildForPerms.value?.my_permission_slugs?.includes(
        GUILD_PERMISSION_MANAGE_ROULETTE
      )
  );

  const canManageRouletteDkpCoefficients = computed(() => canManageRoulette.value);

  async function loadRoulettePage() {
    guildRouletteAccessNotFound.value = false;
    await Promise.all([loadRoster(), loadGuildSettingsForRoulette()]);
  }

  watch(guildId, loadRoulettePage, { immediate: true });

  /** Результат рулетки — показываем в шапке карточки (справа). */
  const wheelSpinResult = ref<string | null>(null);
  const winnerDisplayKey = ref(0);
  const winnerBannerDismissed = ref(false);
  const eliminationMode = ref(false);
  const useDkpCoefficients = ref(false);
  const eliminationActive = ref(false);
  const eliminationAwaitingRemoteWinner = ref(false);
  let eliminationNextSpinTimer: ReturnType<typeof setTimeout> | null = null;

  const showWheelWinnerBanner = computed(() => {
    if (winnerBannerDismissed.value) return false;
    const name = wheelSpinResult.value?.trim();
    if (!name) return false;
    const opts = wheelOptions.value;
    if (opts.length === 1 && opts[0] === WHEEL_EMPTY_PLACEHOLDER) return false;
    return true;
  });

  function clearEliminationNextSpinTimer() {
    if (eliminationNextSpinTimer === null) return;
    clearTimeout(eliminationNextSpinTimer);
    eliminationNextSpinTimer = null;
  }

  function getWheelEntryCoefficient(entry: WheelEntry): number {
    if (entry.kind === 'external') {
      const override = rouletteDkpCoefficientByExternalId.value[entry.id];
      return override !== undefined && Number.isFinite(override) && override >= 0
        ? override
        : 1;
    }
    const override = rouletteDkpCoefficientByCharacterId.value[entry.character_id];
    if (override !== undefined && Number.isFinite(override) && override >= 0) {
      return override;
    }
    const member = roster.value.find((m) => m.character_id === entry.character_id);
    const coefficient = Number(member?.dkp_coefficient);
    return Number.isFinite(coefficient) && coefficient >= 0 ? coefficient : 1;
  }

  function getWheelEntryWeight(entry: WheelEntry): number {
    if (!useDkpCoefficients.value) return 1;
    const coefficient = getWheelEntryCoefficient(entry);
    if (!eliminationMode.value) return coefficient;
    const safeCoefficient = coefficient > 0 ? coefficient : 0.01;
    return 1 / safeCoefficient;
  }

  const wheelCoefficientValues = computed(() =>
    wheelEntries.value.map((entry) => getWheelEntryCoefficient(entry))
  );

  const wheelWeights = computed(() =>
    wheelEntries.value.map((entry) => getWheelEntryWeight(entry))
  );

  function requestNextEliminationSpin() {
    clearEliminationNextSpinTimer();
    eliminationNextSpinTimer = setTimeout(() => {
      eliminationNextSpinTimer = null;
      if (!eliminationActive.value || !eliminationMode.value) return;
      if (!canManageRoulette.value || isWheelSpinning.value) return;
      if (wheelEntries.value.length <= 1) return;
      if (remoteSpin.value) {
        requestSpin(wheelSpinDurationMs.value, wheelWeights.value);
        return;
      }
      spinWheelRef.value?.spin?.();
    }, 650);
  }

  async function onSpinWheelResult(value: string | null, index: number | null = null) {
    if (
      eliminationActive.value &&
      eliminationMode.value &&
      wheelEntries.value.length > 1 &&
      index !== null
    ) {
      await spinWheelRef.value?.animateRemoveSegment?.(index);

      if (!canManageRoulette.value) {
        wheelSpinResult.value = null;
        winnerBannerDismissed.value = true;
        eliminationAwaitingRemoteWinner.value = true;
        return;
      }

      removeWheelEntryAtIndex(index);
      const remaining = wheelEntries.value;
      if (remaining.length === 1) {
        wheelSpinResult.value = getWheelEntryName(remaining[0]) ?? value;
        winnerBannerDismissed.value = false;
        winnerDisplayKey.value += 1;
        eliminationActive.value = false;
        eliminationAwaitingRemoteWinner.value = false;
        return;
      }
      wheelSpinResult.value = null;
      winnerBannerDismissed.value = true;
      requestNextEliminationSpin();
      return;
    }

    wheelSpinResult.value = value;
    winnerBannerDismissed.value = false;
    eliminationActive.value = false;
    eliminationAwaitingRemoteWinner.value = false;
    const opts = wheelOptions.value;
    const placeholder = opts.length === 1 && opts[0] === WHEEL_EMPTY_PLACEHOLDER;
    if (value?.trim() && !placeholder) {
      winnerDisplayKey.value += 1;
    }
  }

  function dismissWheelWinnerBanner() {
    winnerBannerDismissed.value = true;
  }

  function onSpinWheelStart() {
    clearEliminationNextSpinTimer();
    eliminationActive.value =
      eliminationMode.value && wheelEntries.value.length > 1;
    eliminationAwaitingRemoteWinner.value = false;
    wheelSpinResult.value = null;
    winnerBannerDismissed.value = false;
  }

  watch([eliminationMode, wheelEntries], ([mode, entries]) => {
    if (eliminationAwaitingRemoteWinner.value && entries.length === 1) {
      wheelSpinResult.value = getWheelEntryName(entries[0]);
      winnerBannerDismissed.value = false;
      winnerDisplayKey.value += 1;
      eliminationAwaitingRemoteWinner.value = false;
    }
    if (mode && entries.length > 1) return;
    eliminationActive.value = false;
    clearEliminationNextSpinTimer();
  });

  watch(wheelOptions, (opts) => {
    if (opts.length === 0 || (opts.length === 1 && opts[0] === WHEEL_EMPTY_PLACEHOLDER)) {
      wheelSpinResult.value = null;
    }
  });

  /** Строка для `Input` (modelValue — только string). */
  const wheelSpinDurationSeconds = ref(String(WHEEL_SPIN_SEC_DEFAULT));

  const wheelSpinDurationMs = computed(() => {
    let s = Number(wheelSpinDurationSeconds.value);
    if (!Number.isFinite(s)) s = WHEEL_SPIN_SEC_DEFAULT;
    s = Math.min(WHEEL_SPIN_SEC_MAX, Math.max(WHEEL_SPIN_SEC_MIN, s));
    return Math.round(s * 1000);
  });

  function clampWheelSpinSecondsField() {
    let s = Number(wheelSpinDurationSeconds.value);
    if (!Number.isFinite(s)) s = WHEEL_SPIN_SEC_DEFAULT;
    const clamped = Math.min(
      WHEEL_SPIN_SEC_MAX,
      Math.max(WHEEL_SPIN_SEC_MIN, s)
    );
    wheelSpinDurationSeconds.value = String(clamped);
  }

  const spinWheelRef = shallowRef<GuildRouletteSpinWheelExpose | null>(null);

  /**
   * Сеттер для template-ref: используется виджетом, чтобы записать инстанс
   * SpinWheel в `spinWheelRef` (через `reactive(model)` напрямую `model.spinWheelRef.value`
   * становится недоступен, т.к. ref разворачивается).
   */
  function setSpinWheelInstance(instance: GuildRouletteSpinWheelExpose | null) {
    spinWheelRef.value = instance;
  }

  /** Идёт ли вращение (для блокировки изменений списка во время спина). */
  const isWheelSpinning = computed(() => {
    const w = spinWheelRef.value;
    if (!w?.isSpinning) return false;
    return !!unref(w.isSpinning);
  });

  /** Обратный отсчёт вращения для подсказки рядом с полем длительности. */
  const wheelSpinCountdownSeconds = computed(() => {
    const w = spinWheelRef.value;
    if (!w?.spinCountdownSeconds) return null;
    return unref(w.spinCountdownSeconds);
  });

  const {
    socketConfigured,
    socketConnected,
    socketConnectError,
    socketUsesExplicitUrl,
    remoteSpin,
    requestSpin,
    enrollmentOpen,
    setEnrollmentOpen,
    addEntryViaServer,
    removeEntryViaServer,
  } = useGuildRouletteWheelSocket({
    guildId,
    wheelEntries,
    spinWheelRef,
    eliminationMode,
    useDkpCoefficients,
    dkpCoefficientOverrides: rouletteDkpCoefficientByCharacterId,
    externalDkpCoefficientOverrides: rouletteDkpCoefficientByExternalId,
    canManageRouletteWheel: canManageRoulette,
  });

  /** Можно менять состав колеса (целиком): есть права + не идёт вращение. */
  const canEditWheelEntries = computed(
    () => canManageRoulette.value && !isWheelSpinning.value && !eliminationActive.value
  );

  const authStore = useAuthStore();
  const currentUserId = computed(() => authStore.user?.id ?? null);

  /** Является ли текущий пользователь участником гильдии (есть хотя бы один свой персонаж). */
  const isCurrentUserGuildMember = computed(() => {
    const uid = currentUserId.value;
    if (!uid) return false;
    return roster.value.some((m) => m.user_id === uid);
  });

  /** Все персонажи текущего пользователя в этой гильдии. */
  const myCharactersInGuild = computed<GuildRosterMember[]>(() => {
    const uid = currentUserId.value;
    if (!uid) return [];
    return roster.value.filter((m) => m.user_id === uid);
  });

  /** Какие из моих персонажей уже на колесе. */
  const myCharactersOnWheel = computed(() =>
    myCharactersInGuild.value.filter((m) => isInWheel(m.character_id))
  );

  /** Мои персонажи, ещё не добавленные на колесо. */
  const myCharactersAvailable = computed(() =>
    myCharactersInGuild.value.filter((m) => !isInWheel(m.character_id))
  );

  /**
   * Может ли пользователь добавить себя на колесо: набор открыт, идут не вращение,
   * у пользователя есть хотя бы один свободный персонаж, и он рядовой участник
   * (для офицера с правом manage roulette уже доступна полная панель).
   */
  const canParticipateInRoulette = computed(
    () =>
      enrollmentOpen.value &&
      !isWheelSpinning.value &&
      isCurrentUserGuildMember.value &&
      myCharactersAvailable.value.length > 0
  );

  /** Можно ли убрать своих персонажей с колеса (даже если набор закрыт — пока не идёт спин). */
  const canRemoveOwnCharacter = computed(
    () => enrollmentOpen.value && !isWheelSpinning.value
  );

  /**
   * Добавить своего персонажа на колесо. Менеджер использует общий канал
   * (watcher на `wheelEntries` шлёт полный список). Рядовой пользователь —
   * отдельный сокет-ивент `roulette:entries:add`, потому что watcher
   * для него не отправляет изменения.
   */
  function addOwnCharacterToWheel(characterId: number) {
    if (!enrollmentOpen.value || isWheelSpinning.value) return;
    const member = roster.value.find((m) => m.character_id === characterId);
    if (!member) return;
    if (member.user_id !== currentUserId.value) return;
    if (isInWheel(characterId)) return;
    if (canManageRoulette.value) {
      wheelEntries.value = [
        ...wheelEntries.value,
        { kind: 'guild', character_id: characterId },
      ];
      return;
    }
    addEntryViaServer({ kind: 'guild', character_id: characterId });
  }

  function removeOwnCharacterFromWheel(characterId: number) {
    const member = roster.value.find((m) => m.character_id === characterId);
    if (!member) return;
    if (member.user_id !== currentUserId.value) return;
    if (canManageRoulette.value) {
      removeGuildFromWheel(characterId);
      return;
    }
    removeEntryViaServer({ kind: 'guild', character_id: characterId });
  }

  function openEnrollment() {
    setEnrollmentOpen(true);
  }

  function closeEnrollment() {
    setEnrollmentOpen(false);
  }

  /**
   * Выбор персонажа: открыт диалог + список кандидатов (используется в UI,
   * когда у пользователя несколько свободных персонажей в гильдии).
   */
  const characterPickerOpen = ref(false);

  function openCharacterPicker() {
    if (!canParticipateInRoulette.value) return;
    const onlyCharacter = myCharactersAvailable.value[0];
    if (myCharactersAvailable.value.length === 1 && onlyCharacter) {
      addOwnCharacterToWheel(onlyCharacter.character_id);
      return;
    }
    characterPickerOpen.value = true;
  }

  function closeCharacterPicker() {
    characterPickerOpen.value = false;
  }

  function pickCharacterToParticipate(characterId: number) {
    addOwnCharacterToWheel(characterId);
    closeCharacterPicker();
  }

  /**
   * Карта user_id → цвет: только для пользователей, у которых на колесе ≥ 2 персонажей.
   * Цвет назначается по порядку появления на колесе, чтобы был стабилен между ререндерами.
   */
  const userColorByUserId = computed<Map<number, UserColorTheme>>(() => {
    const result = new Map<number, UserColorTheme>();
    const counts = new Map<number, number>();
    for (const e of wheelEntries.value) {
      if (e.kind !== 'guild') continue;
      const m = roster.value.find((rm) => rm.character_id === e.character_id);
      const uid = m?.user_id ?? null;
      if (uid === null) continue;
      counts.set(uid, (counts.get(uid) ?? 0) + 1);
    }
    let colorIndex = 0;
    for (const e of wheelEntries.value) {
      if (e.kind !== 'guild') continue;
      const m = roster.value.find((rm) => rm.character_id === e.character_id);
      const uid = m?.user_id ?? null;
      if (uid === null) continue;
      if ((counts.get(uid) ?? 0) < 2) continue;
      if (result.has(uid)) continue;
      result.set(uid, getUserColorByIndex(colorIndex));
      colorIndex += 1;
    }
    return result;
  });

  function getEntryUserColor(entry: WheelEntry): UserColorTheme | null {
    if (entry.kind !== 'guild') return null;
    const m = roster.value.find((rm) => rm.character_id === entry.character_id);
    const uid = m?.user_id ?? null;
    if (uid === null) return null;
    return userColorByUserId.value.get(uid) ?? null;
  }

  function getMemberUserColor(member: GuildRosterMember): UserColorTheme | null {
    if (member.user_id === null) return null;
    return userColorByUserId.value.get(member.user_id) ?? null;
  }

  function formatDkpCoefficientValue(value: number | undefined): string {
    const coefficient = Number(value);
    const safe = Number.isFinite(coefficient) && coefficient >= 0 ? coefficient : 1;
    return Number.isInteger(safe) ? String(safe) : String(Number(safe.toFixed(2)));
  }

  function getDkpCoefficientDraft(member: GuildRosterMember): string {
    return (
      dkpCoefficientDraftByCharacterId.value[member.character_id] ??
      formatDkpCoefficientValue(
        rouletteDkpCoefficientByCharacterId.value[member.character_id] ??
          member.dkp_coefficient
      )
    );
  }

  function setDkpCoefficientDraft(characterId: number, value: string) {
    dkpCoefficientDraftByCharacterId.value = {
      ...dkpCoefficientDraftByCharacterId.value,
      [characterId]: value,
    };
    dkpCoefficientErrorByCharacterId.value = {
      ...dkpCoefficientErrorByCharacterId.value,
      [characterId]: '',
    };
  }

  function getDkpCoefficientError(characterId: number): string {
    return dkpCoefficientErrorByCharacterId.value[characterId] ?? '';
  }

  function getWheelEntryDkpCoefficientDraft(entry: WheelEntry): string {
    if (entry.kind === 'external') {
      return (
        externalDkpCoefficientDraftById.value[entry.id] ??
        formatDkpCoefficientValue(getWheelEntryCoefficient(entry))
      );
    }
    return (
      dkpCoefficientDraftByCharacterId.value[entry.character_id] ??
      formatDkpCoefficientValue(getWheelEntryCoefficient(entry))
    );
  }

  function setWheelEntryDkpCoefficientDraft(entry: WheelEntry, value: string) {
    if (entry.kind === 'external') {
      externalDkpCoefficientDraftById.value = {
        ...externalDkpCoefficientDraftById.value,
        [entry.id]: value,
      };
      externalDkpCoefficientErrorById.value = {
        ...externalDkpCoefficientErrorById.value,
        [entry.id]: '',
      };
      return;
    }
    setDkpCoefficientDraft(entry.character_id, value);
  }

  function applyWheelEntryDkpCoefficient(entry: WheelEntry) {
    if (!canManageRouletteDkpCoefficients.value) return;
    const raw = getWheelEntryDkpCoefficientDraft(entry).trim().replace(',', '.');
    const value = Number(raw);
    if (!Number.isFinite(value) || value < 0 || value > 999) {
      if (entry.kind === 'external') {
        externalDkpCoefficientErrorById.value = {
          ...externalDkpCoefficientErrorById.value,
          [entry.id]: 'Коэффициент от 0 до 999.',
        };
        return;
      }
      dkpCoefficientErrorByCharacterId.value = {
        ...dkpCoefficientErrorByCharacterId.value,
        [entry.character_id]: 'Коэффициент от 0 до 999.',
      };
      return;
    }

    if (entry.kind === 'external') {
      rouletteDkpCoefficientByExternalId.value = {
        ...rouletteDkpCoefficientByExternalId.value,
        [entry.id]: value,
      };
      externalDkpCoefficientErrorById.value = {
        ...externalDkpCoefficientErrorById.value,
        [entry.id]: '',
      };
      externalDkpCoefficientDraftById.value = {
        ...externalDkpCoefficientDraftById.value,
        [entry.id]: formatDkpCoefficientValue(value),
      };
      return;
    }

    rouletteDkpCoefficientByCharacterId.value = {
      ...rouletteDkpCoefficientByCharacterId.value,
      [entry.character_id]: value,
    };
    dkpCoefficientErrorByCharacterId.value = {
      ...dkpCoefficientErrorByCharacterId.value,
      [entry.character_id]: '',
    };
    setDkpCoefficientDraft(entry.character_id, formatDkpCoefficientValue(value));
  }

  function resetWheelEntryDkpCoefficient(entry: WheelEntry) {
    if (!canManageRouletteDkpCoefficients.value) return;
    if (entry.kind === 'external') {
      const { [entry.id]: _removed, ...nextOverrides } =
        rouletteDkpCoefficientByExternalId.value;
      const { [entry.id]: _removedDraft, ...nextDrafts } =
        externalDkpCoefficientDraftById.value;
      rouletteDkpCoefficientByExternalId.value = nextOverrides;
      externalDkpCoefficientDraftById.value = nextDrafts;
      externalDkpCoefficientErrorById.value = {
        ...externalDkpCoefficientErrorById.value,
        [entry.id]: '',
      };
      return;
    }
    const { [entry.character_id]: _removed, ...nextOverrides } =
      rouletteDkpCoefficientByCharacterId.value;
    const { [entry.character_id]: _removedDraft, ...nextDrafts } =
      dkpCoefficientDraftByCharacterId.value;
    rouletteDkpCoefficientByCharacterId.value = nextOverrides;
    dkpCoefficientDraftByCharacterId.value = nextDrafts;
    dkpCoefficientErrorByCharacterId.value = {
      ...dkpCoefficientErrorByCharacterId.value,
      [entry.character_id]: '',
    };
  }

  function applyRouletteDkpCoefficient(member: GuildRosterMember) {
    if (!canManageRouletteDkpCoefficients.value) return;
    const raw = getDkpCoefficientDraft(member).trim().replace(',', '.');
    const value = Number(raw);
    if (!Number.isFinite(value) || value < 0 || value > 999) {
      dkpCoefficientErrorByCharacterId.value = {
        ...dkpCoefficientErrorByCharacterId.value,
        [member.character_id]: 'Коэффициент от 0 до 999.',
      };
      return;
    }

    rouletteDkpCoefficientByCharacterId.value = {
      ...rouletteDkpCoefficientByCharacterId.value,
      [member.character_id]: value,
    };
    dkpCoefficientErrorByCharacterId.value = {
      ...dkpCoefficientErrorByCharacterId.value,
      [member.character_id]: '',
    };
    setDkpCoefficientDraft(member.character_id, formatDkpCoefficientValue(value));
  }

  return {
    roster,
    loading,
    error,
    guildRouletteAccessNotFound,
    searchQuery,
    filteredRoster,
    wheelEntries,
    wheelOptions,
    isInWheel,
    addToWheel,
    addAllRosterToWheel,
    allRosterAlreadyOnWheel,
    resetWheelEntries,
    removeGuildFromWheel,
    removeExternalFromWheel,
    externalWheelNickname,
    externalWheelHintError,
    addExternalParticipantByNickname,
    importWheelExcelLoading,
    importWheelExcelError,
    importWheelExcelFromFile,
    clearImportWheelExcelError,
    canManageRoulette,
    canManageRouletteDkpCoefficients,
    canEditWheelEntries,
    eliminationMode,
    useDkpCoefficients,
    eliminationActive,
    wheelCoefficientValues,
    wheelWeights,
    wheelSpinResult,
    winnerDisplayKey,
    showWheelWinnerBanner,
    onSpinWheelResult,
    dismissWheelWinnerBanner,
    onSpinWheelStart,
    wheelSpinDurationSeconds,
    wheelSpinDurationMs,
    clampWheelSpinSecondsField,
    spinWheelRef,
    setSpinWheelInstance,
    isWheelSpinning,
    wheelSpinCountdownSeconds,
    socketConfigured,
    socketConnected,
    socketConnectError,
    socketUsesExplicitUrl,
    remoteSpin,
    requestSpin,
    enrollmentOpen,
    openEnrollment,
    closeEnrollment,
    currentUserId,
    isCurrentUserGuildMember,
    myCharactersInGuild,
    myCharactersOnWheel,
    myCharactersAvailable,
    canParticipateInRoulette,
    canRemoveOwnCharacter,
    addOwnCharacterToWheel,
    removeOwnCharacterFromWheel,
    characterPickerOpen,
    openCharacterPicker,
    closeCharacterPicker,
    pickCharacterToParticipate,
    userColorByUserId,
    getEntryUserColor,
    getMemberUserColor,
    getDkpCoefficientDraft,
    setDkpCoefficientDraft,
    applyRouletteDkpCoefficient,
    getWheelEntryDkpCoefficientDraft,
    setWheelEntryDkpCoefficientDraft,
    applyWheelEntryDkpCoefficient,
    resetWheelEntryDkpCoefficient,
    getDkpCoefficientError,
    getExternalDkpCoefficientError: (id: string) =>
      externalDkpCoefficientErrorById.value[id] ?? '',
  };
}

export type GuildRouletteModel = ReturnType<typeof useGuildRoulette>;
