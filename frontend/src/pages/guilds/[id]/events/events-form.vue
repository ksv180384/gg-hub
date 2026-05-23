<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button, Input, BackIconButton } from '@/shared/ui';
import { guildsApi, type GuildRosterMember } from '@/shared/api/guildsApi';
import {
  eventHistoryApi,
  type EventHistoryItem,
  type CreateEventHistoryPayload,
  type UpdateEventHistoryPayload,
} from '@/shared/api/eventHistoryApi';
import {
  eventHistoryTitlesApi,
  type EventHistoryTitleDto,
} from '@/shared/api/eventHistoryTitlesApi';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { parseParticipantNicknamesFromXlsxFile } from '@/shared/lib/eventHistoryParticipantsXlsxImport';
import { DKP_COEFFICIENT_MAX, isValidDkpCoefficient } from '@/shared/lib/dkpValidation';
import { useEventHistoryTitlesAdmin } from '@/features/guild-event-history-titles';
import { EventHistoryTitlesDialog } from '@/widgets/guild-event-history-titles';
import EventsFormTabsHeader from './ui/EventsFormTabsHeader.vue';
import EventsFormInformationTab from './ui/EventsFormInformationTab.vue';
import EventsFormParticipantsTab from './ui/EventsFormParticipantsTab.vue';
import EventsFormScreenshotsTab from './ui/EventsFormScreenshotsTab.vue';
import {
  type EventsFormParticipant,
  type EventsFormTabId,
} from './events-form-types';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const eventHistoryId = computed(() =>
  route.params.eventHistoryId ? Number(route.params.eventHistoryId) : null
);
const isEdit = computed(() => eventHistoryId.value != null);

type ScreenshotRow = {
  url?: string;
  title: string;
  file?: File;
  previewUrl?: string;
};

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const dkpEnabled = ref(false);
const distributeDkpToParticipants = ref(false);

const titlesAdmin = reactive(useEventHistoryTitlesAdmin({
  dkpEnabled: () => dkpEnabled.value,
}));

const roster = ref<GuildRosterMember[]>([]);
const loadingRoster = ref(false);

const importParticipantsLoading = ref(false);
const activeTab = ref<EventsFormTabId>('information');
const importParticipantsError = ref('');

const participantsExcelImportHint =
  'Файл Excel: в первом столбце — один ник на строку (тот же формат, что при скачивании списка участников).\n\n'
  + '• Ник из состава гильдии — участник добавится как член гильдии (регистр букв не важен).\n'
  + '• Ник не найден в составе — добавится как сторонний; в списке «Приняли участие» такая строка подсвечена жёлтым.';

const form = ref({
  title: '',
  description: '',
  occurred_at: '',
  dkp_base_points: '',
  participants: [] as EventsFormParticipant[],
  externalNickname: '',
  screenshots: [] as ScreenshotRow[],
});

const titleSuggestions = ref<EventHistoryTitleDto[]>([]);
const showTitleSuggestions = ref(false);
const titleSuggestionsError = ref('');
const editingTitleId = ref<number | null>(null);
const editingTitleName = ref('');
const editTitleDialogOpen = ref(false);
const editTitleDialogLoading = ref(false);
const deleteTitleDialogOpen = ref(false);
const deleteTitleDialogLoading = ref(false);
const deleteTitleTarget = ref<EventHistoryTitleDto | null>(null);
let titleSearchTimeout: number | undefined;

const guildParticipants = computed(() =>
  form.value.participants.filter((p) => p.character_id)
);
const externalParticipants = computed(() =>
  form.value.participants.filter((p) => !p.character_id && p.external_name)
);
const hasParticipants = computed(
  () => guildParticipants.value.length > 0 || externalParticipants.value.length > 0
);

const totalParticipantsCount = computed(
  () => guildParticipants.value.length + externalParticipants.value.length
);

function isMemberSelected(member: GuildRosterMember): boolean {
  return form.value.participants.some((p) => p.character_id === member.character_id);
}

function toDatetimeLocal(iso: string): string {
  const d = new Date(iso);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${day}T${h}:${min}`;
}

function fromDatetimeLocal(v: string): string | null {
  if (!v) return null;
  return new Date(v).toISOString();
}

function formatDkpBasePoints(value: number | null | undefined): string {
  if (value == null) return '';
  return String(value);
}

function resolveDkpBasePointsForPayload(): number | null | undefined {
  const raw = form.value.dkp_base_points.trim();
  if (!raw) return isEdit.value ? null : undefined;
  const value = Number(raw);
  if (!Number.isFinite(value) || value < 0) return isEdit.value ? null : undefined;
  return Math.trunc(value);
}

async function loadGuildContext() {
  if (!guildId.value) return;
  try {
    const guild = await guildsApi.getGuildForSettings(guildId.value);
    dkpEnabled.value = guild.dkp_enabled ?? false;
  } catch {
    dkpEnabled.value = false;
  }
}

async function loadRoster() {
  if (!guildId.value) return;
  loadingRoster.value = true;
  try {
    roster.value = (await guildsApi.getGuildRoster(guildId.value)).members;
  } catch {
    roster.value = [];
  } finally {
    loadingRoster.value = false;
  }
}

function participantDkpFromRoster(characterId: number): Pick<EventsFormParticipant, 'dkp_coefficient' | 'dkp_points_override'> {
  const member = roster.value.find((m) => m.character_id === characterId);
  return {
    dkp_coefficient: member?.dkp_coefficient ?? 1,
    dkp_points_override: null,
  };
}

function toggleGuildParticipant(characterId: number) {
  const idx = form.value.participants.findIndex((p) => p.character_id === characterId);
  if (idx !== -1) {
    form.value.participants.splice(idx, 1);
    return;
  }
  form.value.participants.push({
    character_id: characterId,
    ...participantDkpFromRoster(characterId),
  });
}

function addExternalParticipant() {
  const nick = form.value.externalNickname.trim();
  if (!nick) return;

  const member = findRosterMemberByNickname(nick);
  const participant: EventsFormParticipant = member
    ? {
        character_id: member.character_id,
        external_name: null,
        dkp_coefficient: member.dkp_coefficient ?? 1,
        dkp_points_override: null,
      }
    : { character_id: null, external_name: nick, dkp_coefficient: 1, dkp_points_override: null };

  const key = participantKey(participant);
  if (form.value.participants.some((p) => participantKey(p) === key)) {
    form.value.externalNickname = '';
    return;
  }

  form.value.participants.push(participant);
  form.value.externalNickname = '';
}

function removeParticipant(p: EventsFormParticipant) {
  form.value.participants = form.value.participants.filter((x) => x !== p);
}

function participantKey(p: EventsFormParticipant): string {
  if (p.character_id != null) {
    return `c:${p.character_id}`;
  }
  return `e:${(p.external_name ?? '').toLowerCase()}`;
}

function findRosterMemberByNickname(raw: string): GuildRosterMember | undefined {
  const q = raw.trim().toLowerCase();
  if (!q) return undefined;
  return roster.value.find((m) => m.name.trim().toLowerCase() === q);
}

async function onParticipantsXlsxChange(ev: Event) {
  importParticipantsError.value = '';
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;

  importParticipantsLoading.value = true;
  importParticipantsError.value = '';
  try {
    const nicknames = await parseParticipantNicknamesFromXlsxFile(file);
    if (!nicknames.length) {
      importParticipantsError.value = 'В файле нет ников в первом столбце.';
      return;
    }
    const existing = new Set(form.value.participants.map(participantKey));
    let added = 0;
    for (const nick of nicknames) {
      const member = findRosterMemberByNickname(nick);
      const p: EventsFormParticipant = member
        ? {
            character_id: member.character_id,
            external_name: null,
            dkp_coefficient: member.dkp_coefficient ?? 1,
            dkp_points_override: null,
          }
        : { character_id: null, external_name: nick.trim(), dkp_coefficient: 1, dkp_points_override: null };
      const key = participantKey(p);
      if (existing.has(key)) continue;
      existing.add(key);
      form.value.participants.push(p);
      added += 1;
    }
    if (!added) {
      importParticipantsError.value = 'Все строки из файла уже есть в списке.';
    }
  } catch (e: unknown) {
    importParticipantsError.value =
      e instanceof Error ? e.message : 'Не удалось прочитать Excel-файл.';
  } finally {
    importParticipantsLoading.value = false;
  }
}

async function loadEventIfEdit() {
  if (!isEdit.value || !guildId.value || !eventHistoryId.value) return;
  loading.value = true;
  try {
    const item: EventHistoryItem = await eventHistoryApi.get(
      guildId.value,
      eventHistoryId.value
    );
    form.value.title = item.title;
    form.value.description = item.description ?? '';
    form.value.occurred_at = item.occurred_at ? toDatetimeLocal(item.occurred_at) : '';
    form.value.dkp_base_points = formatDkpBasePoints(item.dkp?.base_points);
    distributeDkpToParticipants.value = item.dkp?.distribute_to_participants ?? false;
    form.value.participants = (item.participants ?? []).map((p) => ({
      character_id: p.character_id,
      external_name: p.character_id ? null : p.external_name,
      dkp_coefficient: p.dkp?.coefficient ?? 1,
      dkp_points_override: p.dkp?.points_override ?? null,
    }));
    form.value.screenshots = (item.screenshots ?? []).map((s) => ({
      url: s.url,
      title: s.title ?? '',
    }));
  } finally {
    loading.value = false;
  }
}

async function searchTitleSuggestions(query: string) {
  if (titleSearchTimeout) {
    clearTimeout(titleSearchTimeout);
  }
  titleSearchTimeout = window.setTimeout(async () => {
    try {
      titleSuggestionsError.value = '';
      titleSuggestions.value = await eventHistoryTitlesApi.search(query, 10);
      showTitleSuggestions.value = titleSuggestions.value.length > 0;
      syncDistributeFlagFromTitleName(query);
    } catch (e: unknown) {
      titleSuggestionsError.value =
        e instanceof Error ? e.message : 'Не удалось загрузить варианты названий.';
      titleSuggestions.value = [];
      showTitleSuggestions.value = false;
    }
  }, 200);
}

function syncDistributeFlagFromTitleName(name: string) {
  const trimmed = name.trim().toLowerCase();
  if (!trimmed) {
    distributeDkpToParticipants.value = false;
    return;
  }
  const match = titleSuggestions.value.find((s) => s.name.trim().toLowerCase() === trimmed);
  distributeDkpToParticipants.value = match?.distribute_dkp_to_participants ?? false;
}

function applyTitleSuggestion(suggestion: EventHistoryTitleDto) {
  form.value.title = suggestion.name;
  distributeDkpToParticipants.value = suggestion.distribute_dkp_to_participants ?? false;
  if (dkpEnabled.value) {
    if (suggestion.distribute_dkp_to_participants) {
      form.value.dkp_base_points = '';
    } else {
      form.value.dkp_base_points = formatDkpBasePoints(suggestion.dkp_base_points);
    }
  }
  showTitleSuggestions.value = false;
}

function startEditTitleSuggestion(suggestion: EventHistoryTitleDto) {
  editingTitleId.value = suggestion.id;
  editingTitleName.value = suggestion.name;
  editTitleDialogOpen.value = true;
}

async function saveEditTitleSuggestion() {
  if (!editingTitleId.value) return;
  const newName = editingTitleName.value.trim();
  if (!newName) {
    return;
  }
  try {
    editTitleDialogLoading.value = true;
    titleSuggestionsError.value = '';
    const updated = await eventHistoryTitlesApi.update(editingTitleId.value, { name: newName });
    const idx = titleSuggestions.value.findIndex((s) => s.id === editingTitleId.value);
    if (idx !== -1) {
      titleSuggestions.value[idx] = updated;
    }
    // если текущее значение совпадает с редактируемым, обновим и поле
    const current = titleSuggestions.value.find((s) => s.id === editingTitleId.value);
    if (current && form.value.title === current.name) {
      form.value.title = updated.name;
    }
    editTitleDialogOpen.value = false;
  } catch {
    titleSuggestionsError.value =
      'Не удалось сохранить название. Возможно, такое название уже существует.';
  } finally {
    editTitleDialogLoading.value = false;
  }
}

async function deleteTitleSuggestion(suggestion: EventHistoryTitleDto) {
  titleSuggestionsError.value = '';
  try {
    await eventHistoryTitlesApi.delete(suggestion.id);
    titleSuggestions.value = titleSuggestions.value.filter((s) => s.id !== suggestion.id);
    if (!titleSuggestions.value.length) {
      showTitleSuggestions.value = false;
    }
  } catch (e: unknown) {
    titleSuggestionsError.value =
      e instanceof Error ? e.message : 'Не удалось удалить название.';
    throw e;
  }
}

function startDeleteTitleSuggestion(suggestion: EventHistoryTitleDto) {
  deleteTitleTarget.value = suggestion;
  titleSuggestionsError.value = '';
  deleteTitleDialogOpen.value = true;
}

async function confirmDeleteTitleSuggestion() {
  if (!deleteTitleTarget.value) return;
  deleteTitleDialogLoading.value = true;
  try {
    await deleteTitleSuggestion(deleteTitleTarget.value);
    deleteTitleDialogOpen.value = false;
    deleteTitleTarget.value = null;
  } catch {
    // Модалка остаётся открытой, ошибка уже показана через titleSuggestionsError
  } finally {
    deleteTitleDialogLoading.value = false;
  }
}

async function submit() {
  if (!guildId.value) return;
  error.value = '';

  if (!form.value.title.trim()) {
    error.value = 'Введите название события.';
    return;
  }

  if (dkpEnabled.value) {
    for (const participant of form.value.participants) {
      if (participant.character_id == null) {
        continue;
      }
      const coefficient = Number(participant.dkp_coefficient ?? 1);
      if (!isValidDkpCoefficient(coefficient)) {
        error.value = `Коэффициент ДКП должен быть от 0 до ${DKP_COEFFICIENT_MAX}.`;
        return;
      }
    }
  }

  const payloadBase: CreateEventHistoryPayload | UpdateEventHistoryPayload = {
    title: form.value.title.trim(),
    description: form.value.description.trim() || null,
    occurred_at: fromDatetimeLocal(form.value.occurred_at),
    participants: form.value.participants.map((p) => {
      const base = {
        character_id: p.character_id ?? null,
        external_name: p.external_name ?? null,
      };
      if (!dkpEnabled.value) {
        return base;
      }
      return {
        ...base,
        dkp_coefficient: p.dkp_coefficient ?? 1,
        dkp_points_override: p.dkp_points_override ?? null,
      };
    }),
    screenshots: form.value.screenshots
      .map((s, index) => ({
        url: s.url?.trim(),
        file: s.file,
        title: s.title.trim() || null,
        sort_order: index,
      }))
      .filter((s) => s.file || (s.url?.length ?? 0) > 0),
  };

  if (dkpEnabled.value) {
    payloadBase.distribute_dkp_to_participants = distributeDkpToParticipants.value;
    const dkpBasePoints = resolveDkpBasePointsForPayload();
    if (dkpBasePoints !== undefined) {
      payloadBase.dkp_base_points = dkpBasePoints;
    }
  }

  saving.value = true;
  try {
    if (isEdit.value && eventHistoryId.value) {
      await eventHistoryApi.update(guildId.value, eventHistoryId.value, payloadBase);
    } else {
      await eventHistoryApi.create(guildId.value, payloadBase as CreateEventHistoryPayload);
    }
    router.push({ name: 'guild-events', params: { id: guildId.value } });
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Ошибка сохранения.';
  } finally {
    saving.value = false;
  }
}

function goBack() {
  router.push({ name: 'guild-events', params: { id: guildId.value } });
}

onMounted(async () => {
  const now = new Date();
  if (!isEdit.value) {
    form.value.occurred_at = toDatetimeLocal(now.toISOString());
  }
  await Promise.all([loadGuildContext(), loadRoster(), loadEventIfEdit()]);
});
</script>

<template>
  <div>
    <div class="fixed top-[100px] right-8 z-30 md:hidden">
      <BackIconButton
        aria-label="К событиям"
        title="К событиям"
        @click="goBack"
      />
    </div>
  <div class="space-y-4">
        <div class="relative flex flex-col md:flex-row md:items-start md:gap-3">
          <div class="sticky top-[100px] z-30 hidden shrink-0 self-start md:block">
            <BackIconButton
              aria-label="К событиям"
              title="К событиям"
              @click="goBack"
            />
          </div>

          <div class="min-w-0 w-full flex-1">
            <div class="mb-4 space-y-1">
              <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Редактирование события' : 'Новое событие' }}
              </h1>
              <p v-if="isEdit" class="text-sm text-muted-foreground">
                Вносите изменения и сохраняйте для обновления события
              </p>
            </div>

            <EventsFormTabsHeader v-model:active-tab="activeTab" />

            <div class="space-y-4">
              <EventsFormInformationTab
                v-show="activeTab === 'information'"
                v-model:title="form.title"
                v-model:occurred-at="form.occurred_at"
                v-model:description="form.description"
                v-model:dkp-base-points="form.dkp_base_points"
                :dkp-enabled="dkpEnabled"
                :distribute-dkp-to-participants="distributeDkpToParticipants"
                :show-event-types-button="!isEdit"
                :title-suggestions="titleSuggestions"
                :show-title-suggestions="showTitleSuggestions"
                @search-title-suggestions="searchTitleSuggestions"
                @hide-title-suggestions="showTitleSuggestions = false"
                @apply-title-suggestion="applyTitleSuggestion"
                @edit-title-suggestion="startEditTitleSuggestion"
                @delete-title-suggestion="startDeleteTitleSuggestion"
                @open-event-types="titlesAdmin.openModal()"
              />

              <EventsFormParticipantsTab
                v-show="activeTab === 'participants'"
                v-model:external-nickname="form.externalNickname"
                :guild-id="guildId"
                :dkp-enabled="dkpEnabled"
                :distribute-dkp-to-participants="distributeDkpToParticipants"
                :dkp-base-points="form.dkp_base_points"
                :guild-participants="guildParticipants"
                :roster="roster"
                :loading-roster="loadingRoster"
                :import-participants-loading="importParticipantsLoading"
                :import-participants-error="importParticipantsError"
                :participants-excel-import-hint="participantsExcelImportHint"
                :external-participants="externalParticipants"
                :has-participants="hasParticipants"
                :total-participants-count="totalParticipantsCount"
                :is-member-selected="isMemberSelected"
                @add-external-participant="addExternalParticipant"
                @participants-xlsx-change="onParticipantsXlsxChange"
                @remove-participant="removeParticipant"
                @toggle-guild-participant="toggleGuildParticipant"
              />

              <EventsFormScreenshotsTab
                v-show="activeTab === 'screenshots'"
                v-model:screenshots="form.screenshots"
              />

              <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                <Button type="button" variant="outline" :disabled="saving" @click="goBack">
                  Отмена
                </Button>
                <Button type="button" :disabled="saving" @click="submit">
                  {{
                    saving
                      ? 'Сохранение…'
                      : isEdit
                        ? 'Сохранить изменения'
                        : 'Создать'
                  }}
                </Button>
              </div>

              <p v-if="error" class="text-sm text-destructive">
                {{ error }}
              </p>
            </div>
          </div>
        </div>
  </div>
  <ConfirmDialog
    v-model:open="editTitleDialogOpen"
    :loading="editTitleDialogLoading"
    title="Редактирование названия события"
    confirm-label="Сохранить"
    cancel-label="Отмена"
    confirm-variant="default"
    @confirm="saveEditTitleSuggestion"
  >
    <template #description>
      <div class="space-y-2">
        <p class="text-sm text-muted-foreground">
          Введите новое название для шаблона. Оно будет использоваться в будущих событиях.
        </p>
        <Input
          v-model="editingTitleName"
          type="text"
          maxlength="255"
          placeholder="Новое название события"
          class="mt-1"
        />
        <p v-if="titleSuggestionsError" class="text-xs text-destructive">
          {{ titleSuggestionsError }}
        </p>
      </div>
    </template>
  </ConfirmDialog>
  <ConfirmDialog
    v-model:open="deleteTitleDialogOpen"
    :loading="deleteTitleDialogLoading"
    title="Удалить название события?"
    confirm-label="Удалить"
    cancel-label="Отмена"
    confirm-variant="destructive"
    @confirm="confirmDeleteTitleSuggestion"
  >
    <template #description>
      <div class="space-y-2">
        <p class="text-sm text-muted-foreground">
          Вы уверены, что хотите удалить это название? Его нельзя будет выбрать для новых событий.
        </p>
        <p v-if="deleteTitleTarget" class="text-sm font-medium">
          «{{ deleteTitleTarget.name }}»
        </p>
        <p v-if="titleSuggestionsError" class="text-xs text-destructive">
          {{ titleSuggestionsError }}
        </p>
      </div>
    </template>
  </ConfirmDialog>

  <EventHistoryTitlesDialog
    v-model:open="titlesAdmin.open"
    v-model:form="titlesAdmin.form"
    v-model:edit-form="titlesAdmin.editForm"
    :loading="titlesAdmin.loading"
    :list-error="titlesAdmin.listError"
    :form-error="titlesAdmin.formError"
    :saving="titlesAdmin.saving"
    :deleting-id="titlesAdmin.deletingId"
    :sorted-titles="titlesAdmin.sortedTitles"
    :editing-id="titlesAdmin.editingId"
    :create-form-open="titlesAdmin.createFormOpen"
    :dkp-enabled="dkpEnabled"
    @open-create="titlesAdmin.openCreateForm()"
    @cancel-create="titlesAdmin.cancelCreateForm()"
    @create="titlesAdmin.createTitle()"
    @start-edit="titlesAdmin.startEdit"
    @save-edit="titlesAdmin.saveEdit()"
    @cancel-edit="titlesAdmin.resetEditForm()"
    @delete="titlesAdmin.deleteTitle"
  />
  </div>
</template>
