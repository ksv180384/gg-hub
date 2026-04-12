<script setup lang="ts">
import { ref, computed, watch, shallowRef, unref } from 'vue';
import { useRoute } from 'vue-router';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Label,
  Avatar,
  Badge,
  Tooltip,
} from '@/shared/ui';
import { SpinWheel } from '@/widgets/spin-wheel';
import { GUILD_PERMISSION_MANAGE_ROULETTE } from '@/shared/api/guildPermissionSlugs';
import { guildsApi, type Guild, type GuildRosterMember } from '@/shared/api/guildsApi';
import { parseParticipantNicknamesFromXlsxFile } from '@/shared/lib/eventHistoryParticipantsXlsxImport';
import {
  useGuildAuctionWheelSocket,
  type GuildAuctionSpinWheelExpose,
  type GuildAuctionWheelEntry,
} from '@/shared/lib/useGuildAuctionWheelSocket';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

type WheelEntry = GuildAuctionWheelEntry;

const roster = ref<GuildRosterMember[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const searchQuery = ref('');
/** Участники на колесе: из состава или только ник (из Excel / не из гильдии) */
const wheelEntries = ref<WheelEntry[]>([]);

const wheelExcelInputRef = ref<HTMLInputElement | null>(null);
const importWheelExcelLoading = ref(false);
const importWheelExcelError = ref('');

const wheelExcelImportHint =
  'Первый столбец — один ник в строке. Если ник совпадает с составом гильдии (без учёта регистра), на колесо попадет персонаж из гильдии; иначе — только текст ника. Можно также ввести ник вручную ниже.';

const wheelCardInfoHint =
  'Добавьте участников справа или загрузите список из Excel, затем крутите колесо.\n\nРулетка и список на колесе синхронизируются у всех, кто открыл эту страницу.\n\nИзменять состав колеса и запускать розыгрыш могут только участники с правом «Управление рулеткой» (роли гильдии).\n\nДлительность вращения (секунды): чем больше значение, тем плавнее колесо замедляется перед остановкой.';

const externalWheelNickname = ref('');
const externalWheelHintError = ref('');

/** randomUUID есть не везде (старые браузеры, часть WebView). */
function createWheelExternalId(): string {
  const c = globalThis.crypto as Crypto | undefined;
  if (c && typeof c.randomUUID === 'function') {
    return c.randomUUID();
  }
  return `ext-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 11)}`;
}

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
  return wheelEntries.value.some((x) => x.kind === 'guild' && x.character_id === characterId);
}

function findRosterMemberByNickname(raw: string): GuildRosterMember | undefined {
  const q = raw.trim().toLowerCase();
  if (!q) return undefined;
  return roster.value.find((m) => m.name.trim().toLowerCase() === q);
}

function addToWheel(member: GuildRosterMember) {
  if (isInWheel(member.character_id)) return;
  wheelEntries.value = [...wheelEntries.value, { kind: 'guild', character_id: member.character_id }];
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
}

function removeGuildFromWheel(characterId: number) {
  wheelEntries.value = wheelEntries.value.filter(
    (e) => !(e.kind === 'guild' && e.character_id === characterId)
  );
}

function removeExternalFromWheel(id: string) {
  wheelEntries.value = wheelEntries.value.filter((e) => !(e.kind === 'external' && e.id === id));
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
      (e) => e.kind === 'external' && e.name.trim().toLowerCase() === trimmed.toLowerCase()
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

function openWheelExcelPicker() {
  importWheelExcelError.value = '';
  wheelExcelInputRef.value?.click();
}

async function onWheelExcelChange(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;

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
        if (!next.some((e) => e.kind === 'guild' && e.character_id === member.character_id)) {
          next.push({ kind: 'guild', character_id: member.character_id });
          added += 1;
        }
      } else {
        const trimmed = nick.trim();
        if (!trimmed) continue;
        if (
          !next.some(
            (e) =>
              e.kind === 'external' && e.name.trim().toLowerCase() === trimmed.toLowerCase()
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

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

async function loadRoster() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  loading.value = true;
  error.value = null;
  try {
    roster.value = await guildsApi.getGuildRoster(guildId.value);
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 403) {
      error.value = 'Состав гильдии доступен только участникам.';
    } else {
      error.value = err instanceof Error ? err.message : 'Ошибка загрузки состава';
    }
    roster.value = [];
  } finally {
    loading.value = false;
  }
}

const guildForPerms = ref<Guild | null>(null);

async function loadGuildSettingsForAuction() {
  guildForPerms.value = null;
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  try {
    guildForPerms.value = await guildsApi.getGuildForSettings(guildId.value);
  } catch {
    guildForPerms.value = null;
  }
}

const canManageRoulette = computed(
  () =>
    !!guildForPerms.value?.my_permission_slugs?.includes(GUILD_PERMISSION_MANAGE_ROULETTE)
);

async function loadAuctionPage() {
  await Promise.all([loadRoster(), loadGuildSettingsForAuction()]);
}

watch(guildId, loadAuctionPage, { immediate: true });

/** Результат рулетки — показываем в шапке карточки (справа). */
const wheelSpinResult = ref<string | null>(null);
const winnerDisplayKey = ref(0);

const showWheelWinnerBanner = computed(() => {
  const name = wheelSpinResult.value?.trim();
  if (!name) return false;
  const opts = wheelOptions.value;
  if (opts.length === 1 && opts[0] === 'Добавьте участников') return false;
  return true;
});

function onSpinWheelResult(value: string | null) {
  wheelSpinResult.value = value;
  const opts = wheelOptions.value;
  const placeholder = opts.length === 1 && opts[0] === 'Добавьте участников';
  if (value?.trim() && !placeholder) {
    winnerDisplayKey.value += 1;
  }
}

function onSpinWheelStart() {
  wheelSpinResult.value = null;
}

watch(wheelOptions, (opts) => {
  if (opts.length === 0 || (opts.length === 1 && opts[0] === 'Добавьте участников')) {
    wheelSpinResult.value = null;
  }
});

const WHEEL_SPIN_SEC_MIN = 2;
const WHEEL_SPIN_SEC_MAX = 60;
/** Строка для `Input` (modelValue — только string). */
const wheelSpinDurationSeconds = ref('4');

const wheelSpinDurationMs = computed(() => {
  let s = Number(wheelSpinDurationSeconds.value);
  if (!Number.isFinite(s)) s = 4;
  s = Math.min(WHEEL_SPIN_SEC_MAX, Math.max(WHEEL_SPIN_SEC_MIN, s));
  return Math.round(s * 1000);
});

function clampWheelSpinSecondsField() {
  let s = Number(wheelSpinDurationSeconds.value);
  if (!Number.isFinite(s)) s = 4;
  const clamped = Math.min(
    WHEEL_SPIN_SEC_MAX,
    Math.max(WHEEL_SPIN_SEC_MIN, s)
  );
  wheelSpinDurationSeconds.value = String(clamped);
}

const spinWheelRef = shallowRef<GuildAuctionSpinWheelExpose | null>(null);

/** Обратный отсчёт вращения: для строки с длительностью вместо подсказки «Допустимо 2–60 с.». */
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
} = useGuildAuctionWheelSocket({
  guildId,
  wheelEntries,
  spinWheelRef,
  canManageAuctionWheel: canManageRoulette,
});
</script>

<template>
  <div class="container py-6">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
      <!-- Колесо слева -->
      <div class="min-w-0 w-full max-w-full shrink-0 lg:w-auto">
        <Card class="min-w-0 max-w-full overflow-hidden">
          <CardHeader class="space-y-3">
            <div
              class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4"
            >
              <CardTitle class="flex shrink-0 items-center gap-1.5 text-left">
                Рулетка гильдии
                <Tooltip
                  :content="wheelCardInfoHint"
                  side="top"
                  class="max-w-[min(100vw-2rem,22rem)] whitespace-pre-line text-left"
                >
                  <button
                    type="button"
                    class="inline-flex shrink-0 rounded-sm text-muted-foreground outline-none ring-offset-background transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    aria-label="Справка по рулетке гильдии"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      class="h-4 w-4"
                      aria-hidden="true"
                    >
                      <circle cx="12" cy="12" r="10" />
                      <path d="M12 16v-4" />
                      <path d="M12 8h.01" />
                    </svg>
                  </button>
                </Tooltip>
              </CardTitle>
              <div
                class="relative flex min-h-[2.5rem] w-full min-w-0 items-center justify-center sm:flex-1 sm:justify-end"
                aria-live="polite"
              >
                <Transition name="auction-wheel-winner">
                  <div
                    v-if="showWheelWinnerBanner"
                    :key="winnerDisplayKey"
                    class="flex w-full min-w-0 justify-center sm:w-auto sm:max-w-full sm:justify-end"
                  >
                    <div
                      class="auction-wheel-winner-burst relative inline-flex max-w-full min-w-0 flex-nowrap items-center overflow-hidden rounded-lg border border-amber-700/45 px-3 py-1.5 shadow-md sm:text-left"
                      role="status"
                    >
                      <span
                        class="relative z-10 min-w-0 truncate text-base font-bold leading-none text-amber-950 sm:text-lg"
                      >
                        {{ wheelSpinResult }}
                      </span>
                    </div>
                  </div>
                </Transition>
              </div>
            </div>
          </CardHeader>
          <CardContent class="flex flex-col items-stretch gap-6">
            <p v-if="!socketConfigured" class="text-center text-xs text-muted-foreground">
              Синхронизация отключена
              (<code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">VITE_SOCKET_URL=off</code>).
            </p>
            <p
              v-else-if="socketConnectError"
              class="text-center text-xs text-destructive"
            >
              Не удалось подключиться к синхронизации: {{ socketConnectError }}.
              <template v-if="socketUsesExplicitUrl">
                Проверьте
                <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">VITE_SOCKET_URL</code>
                — адрес должен открываться из браузера (не имя сервиса Docker вроде
                <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">socket_server</code>).
              </template>
              <template v-else>
                Запущен ли контейнер сокет-сервера и прокси
                <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">/socket.io</code>
                в nginx (или dev-прокси Vite)? Перезапустите
                <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">gg-nginx</code>
                после правок конфига.
              </template>
              При отсутствии связи крутить и менять список на колесе доступно только с соответствующими правами.
            </p>
            <p
              v-else-if="socketConfigured && !socketConnected"
              class="text-center text-xs text-amber-800 dark:text-amber-200"
            >
              Подключение к синхронизации…
            </p>
            <p
              v-else-if="socketConfigured && socketConnected && !remoteSpin"
              class="text-center text-xs text-muted-foreground"
            >
              Загрузка состояния рулетки…
            </p>
            <div
              v-if="canManageRoulette"
              class="flex flex-col items-center gap-2 border-b border-border pb-4 sm:flex-row sm:flex-wrap sm:justify-center"
            >
              <div class="flex items-center gap-2">
                <Label for="wheel-spin-seconds" class="shrink-0 whitespace-nowrap text-sm text-muted-foreground">
                  Время вращения, с
                </Label>
                <Input
                  id="wheel-spin-seconds"
                  v-model="wheelSpinDurationSeconds"
                  type="number"
                  :min="WHEEL_SPIN_SEC_MIN"
                  :max="WHEEL_SPIN_SEC_MAX"
                  step="0.5"
                  class="w-[5rem] shrink-0"
                  @blur="clampWheelSpinSecondsField"
                />
              </div>
              <p
                v-if="wheelSpinCountdownSeconds !== null"
                class="max-w-md text-center text-sm font-semibold tabular-nums text-foreground sm:text-left"
                aria-live="polite"
                role="status"
              >
                Осталось {{ wheelSpinCountdownSeconds }}&nbsp;с
              </p>
              <p v-else class="max-w-md text-center text-xs text-muted-foreground sm:text-left">
                Допустимо {{ WHEEL_SPIN_SEC_MIN }}–{{ WHEEL_SPIN_SEC_MAX }} с.
              </p>
            </div>
            <div class="flex justify-center">
              <SpinWheel
                ref="spinWheelRef"
                :options="wheelOptions.length > 0 ? wheelOptions : ['Добавьте участников']"
                :size="360"
                :duration="wheelSpinDurationMs"
                :remote-spin="remoteSpin"
                :show-spin-button="canManageRoulette"
                :spin-disabled="wheelEntries.length === 0"
                :hide-inline-countdown="canManageRoulette"
                @result="onSpinWheelResult"
                @spin-start="onSpinWheelStart"
                @spin-request="requestSpin"
              />
            </div>
            <div v-if="wheelEntries.length > 0" class="space-y-2 text-sm">
              <p class="font-medium text-center sm:text-left">
                На колесе ({{ wheelEntries.length }})
              </p>
              <ul class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-border p-2">
                <li
                  v-for="(e, idx) in wheelEntries"
                  :key="e.kind === 'guild' ? `g-${e.character_id}` : `x-${e.id}`"
                  class="flex items-center justify-between gap-2 rounded px-1 py-0.5 text-xs"
                >
                  <span
                    :class="
                      e.kind === 'external'
                        ? 'rounded-sm bg-amber-500/10 px-1 text-amber-900 dark:bg-amber-400/[0.12] dark:text-amber-200'
                        : 'truncate'
                    "
                  >
                    {{ wheelOptions[idx] }}
                  </span>
                  <Button
                    v-if="canManageRoulette"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 shrink-0 px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    @click="
                      e.kind === 'guild'
                        ? removeGuildFromWheel(e.character_id)
                        : removeExternalFromWheel(e.id)
                    "
                  >
                    ✕
                  </Button>
                </li>
              </ul>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Список участников справа -->
      <Card class="min-w-0 flex-1 lg:max-w-sm">
        <CardHeader>
          <CardTitle>Участники гильдии</CardTitle>
          <p class="text-sm text-muted-foreground">
            <template v-if="canManageRoulette">
              Участники из состава — из списка ниже; гостей — вручную или из Excel.
            </template>
            <template v-else>
              Просмотр состава. Добавлять на колесо могут офицеры с правом «Управление рулеткой».
            </template>
          </p>
          <div class="mt-2 flex min-w-0 items-center gap-2">
            <Input
              v-model="searchQuery"
              type="search"
              placeholder="Поиск по имени…"
              class="min-w-0 flex-1"
            />
            <template v-if="canManageRoulette">
              <input
                ref="wheelExcelInputRef"
                type="file"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                class="sr-only"
                @change="onWheelExcelChange"
              />
              <Tooltip :content="wheelExcelImportHint" side="top" class="max-w-sm shrink-0 text-left">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  class="gap-1.5 shrink-0"
                  aria-label="Загрузить список ников из Excel"
                  :disabled="importWheelExcelLoading"
                  @click="openWheelExcelPicker"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="8" x2="16" y1="13" y2="13" />
                    <line x1="8" x2="16" y1="17" y2="17" />
                  </svg>
                  {{ importWheelExcelLoading ? 'Читаем…' : 'Excel' }}
                </Button>
              </Tooltip>
            </template>
          </div>
          <div v-if="canManageRoulette" class="mt-2 flex flex-wrap gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              :disabled="roster.length === 0 || allRosterAlreadyOnWheel"
              @click="addAllRosterToWheel"
            >
              Добавить всех
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              :disabled="wheelEntries.length === 0"
              @click="resetWheelEntries"
            >
              Сбросить
            </Button>
          </div>
          <p v-if="canManageRoulette && importWheelExcelError" class="mt-2 text-xs text-destructive">
            {{ importWheelExcelError }}
          </p>
          <div v-if="canManageRoulette" class="mt-3 space-y-2">
            <Label for="wheel-external-nick" class="text-muted-foreground">
              Не из состава гильдии
            </Label>
            <div class="flex flex-wrap gap-2">
              <Input
                id="wheel-external-nick"
                v-model="externalWheelNickname"
                type="text"
                placeholder="Ник на колесо"
                class="min-w-0 flex-1"
                @keydown.enter.prevent="addExternalParticipantByNickname"
              />
              <Button type="button" size="sm" @click="addExternalParticipantByNickname">
                На колесо
              </Button>
            </div>
            <p v-if="externalWheelHintError" class="text-xs text-destructive">
              {{ externalWheelHintError }}
            </p>
          </div>
        </CardHeader>
        <CardContent>
          <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
          <p v-else-if="filteredRoster.length === 0" class="text-sm text-muted-foreground">
            {{ searchQuery.trim() ? 'Никого не найдено.' : 'В гильдии пока никого нет.' }}
          </p>
          <ul v-else class="space-y-2">
            <li
              v-for="member in filteredRoster"
              :key="member.character_id"
              class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
            >
              <div class="flex min-w-0 flex-1 items-center gap-3">
                <Avatar
                  :src="member.avatar_url ?? undefined"
                  :alt="member.name"
                  :fallback="avatarFallback(member.name)"
                  class="h-9 w-9 shrink-0"
                />
                <div class="min-w-0">
                  <p class="truncate font-medium text-sm">{{ member.name }}</p>
                  <Badge
                    v-if="member.guild_role"
                    variant="secondary"
                    class="mt-0.5 text-xs"
                  >
                    {{ member.guild_role.name }}
                  </Badge>
                </div>
              </div>
              <Button
                v-if="canManageRoulette && isInWheel(member.character_id)"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="removeGuildFromWheel(member.character_id)"
              >
                Убрать
              </Button>
              <Button
                v-else-if="canManageRoulette"
                variant="default"
                size="sm"
                class="shrink-0"
                @click="addToWheel(member)"
              >
                Добавить
              </Button>
              <span
                v-else-if="isInWheel(member.character_id)"
                class="shrink-0 text-xs text-muted-foreground"
              >
                На колесе
              </span>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.auction-wheel-winner-enter-active {
  transition:
    opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.65s cubic-bezier(0.34, 1.45, 0.64, 1),
    filter 0.45s ease-out;
}
.auction-wheel-winner-leave-active {
  transition: opacity 0.2s ease-out;
}
.auction-wheel-winner-enter-from {
  opacity: 0;
  transform: scale(0.88) translateY(10px);
  filter: blur(3px);
}
.auction-wheel-winner-enter-to {
  opacity: 1;
  transform: scale(1) translateY(0);
  filter: blur(0);
}
.auction-wheel-winner-leave-to {
  opacity: 0;
}

.auction-wheel-winner-burst {
  background: linear-gradient(
    125deg,
    #6b530c 0%,
    #9a7616 18%,
    #c9a227 38%,
    #e3bc3a 50%,
    #f0d060 52%,
    #e3bc3a 55%,
    #b8891c 78%,
    #7a5f0f 100%
  );
  background-size: 220% 220%;
  animation: auction-wheel-gold-shift 5s ease-in-out infinite;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.35),
    0 2px 10px rgba(120, 90, 10, 0.35);
}

.auction-wheel-winner-burst::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  transform: translateX(-130%);
  background: linear-gradient(
    100deg,
    transparent 35%,
    rgba(255, 255, 255, 0.08) 45%,
    rgba(255, 255, 255, 0.65) 50%,
    rgba(255, 255, 255, 0.12) 55%,
    transparent 65%
  );
  animation: auction-wheel-shine 2.4s ease-in-out infinite;
  pointer-events: none;
}

@keyframes auction-wheel-gold-shift {
  0%,
  100% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
}

@keyframes auction-wheel-shine {
  0% {
    transform: translateX(-130%);
  }
  100% {
    transform: translateX(130%);
  }
}
</style>
