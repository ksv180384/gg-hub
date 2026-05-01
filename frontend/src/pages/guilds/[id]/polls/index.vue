<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useSiteContextStore } from '@/stores/siteContext';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
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
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import type { ApiError } from '@/shared/api/errors';
import {
  guildsApi,
  type Guild,
  type GuildPollItem,
  type GuildPollOptionItem,
  type CreateGuildPollPayload,
  type UpdateGuildPollPayload,
} from '@/shared/api/guildsApi';
import NotFoundPage from '@/pages/not-found/index.vue';
import { useGuildPollsSocket } from '@/shared/lib/useGuildPollsSocket';

const route = useRoute();
const siteContext = useSiteContextStore();
const guildId = computed(() => Number(route.params.id));
const guildIdsForSocket = computed<number[]>(() => {
  const id = Number(route.params.id);
  return Number.isFinite(id) && id > 0 ? [id] : [];
});

const guild = ref<Guild | null>(null);
const polls = ref<GuildPollItem[]>([]);
const loading = ref(true);
/** Нет членства в гильдии (403/404). */
const guildPollsAccessNotFound = ref(false);
const error = ref<string | null>(null);

const canAdd = computed(
  () => guild.value?.my_permission_slugs?.includes('dobavliat-gollosovanie') ?? false
);
const canEdit = computed(
  () => guild.value?.my_permission_slugs?.includes('redaktirovat-gollosovanie') ?? false
);
const canClose = computed(
  () => guild.value?.my_permission_slugs?.includes('zakryvat-gollosovanie') ?? false
);
const canReset = computed(
  () => guild.value?.my_permission_slugs?.includes('sbrasyvat-gollosovanie') ?? false
);
const canDelete = computed(
  () => guild.value?.my_permission_slugs?.includes('udaliat-gollosovanie') ?? false
);

const myCharacters = computed(() => guild.value?.my_characters ?? []);

// Форма создания/редактирования
const modalOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formPollId = ref<number | null>(null);
const formTitle = ref('');
const formDescription = ref('');
const formIsAnonymous = ref(true);
const formEndsAt = ref('');
const formOptions = ref<string[]>(['', '']);
const formCharacterId = ref<string>('__none__');
const formSubmitting = ref(false);
const formError = ref<string | null>(null);

const SELECT_NONE = '__none__';

const datetimeLocalMin = computed(() => {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${day}T${h}:${min}`;
});

function toDatetimeLocal(iso: string): string {
  if (!iso) return '';
  const d = new Date(iso);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${day}T${h}:${min}`;
}

function fromDatetimeLocal(s: string): string | null {
  if (!s?.trim()) return null;
  return new Date(s).toISOString();
}

function formatEndsAt(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function addFormOption() {
  if (formOptions.value.length < 20) {
    formOptions.value.push('');
  }
}

function removeFormOption(index: number) {
  if (formOptions.value.length > 2) {
    formOptions.value.splice(index, 1);
  }
}

function openCreate() {
  formMode.value = 'create';
  formPollId.value = null;
  formTitle.value = '';
  formDescription.value = '';
  formIsAnonymous.value = true;
  formEndsAt.value = '';
  formOptions.value = ['', ''];
  formCharacterId.value = myCharacters.value.length === 1
    ? String(myCharacters.value[0].id)
    : SELECT_NONE;
  formError.value = null;
  modalOpen.value = true;
}

function openEdit(poll: GuildPollItem) {
  formMode.value = 'edit';
  formPollId.value = poll.id;
  formTitle.value = poll.title;
  formDescription.value = poll.description ?? '';
  formIsAnonymous.value = poll.is_anonymous ?? true;
  formEndsAt.value = poll.ends_at ? toDatetimeLocal(poll.ends_at) : '';
  formOptions.value =
    poll.options.length >= 2
      ? poll.options.map((o) => o.text)
      : ['', ''];
  formError.value = null;
  modalOpen.value = true;
}

function closeModal() {
  modalOpen.value = false;
}

async function submitForm() {
  formError.value = null;
  const opts = formOptions.value.map((s) => s.trim()).filter(Boolean);
  if (!formTitle.value.trim()) {
    formError.value = 'Укажите название голосования.';
    return;
  }
  if (opts.length < 2) {
    formError.value = 'Добавьте минимум 2 варианта ответа.';
    return;
  }
  formSubmitting.value = true;
  try {
    if (formMode.value === 'create') {
      const payload: CreateGuildPollPayload = {
        title: formTitle.value.trim(),
        description: formDescription.value.trim() || null,
        is_anonymous: formIsAnonymous.value,
        ends_at: fromDatetimeLocal(formEndsAt.value),
        options: opts,
        created_by_character_id:
          formCharacterId.value && formCharacterId.value !== SELECT_NONE
            ? Number(formCharacterId.value)
            : null,
      };
      await guildsApi.createGuildPoll(guildId.value, payload);
    } else {
      const payload: UpdateGuildPollPayload = {
        title: formTitle.value.trim(),
        description: formDescription.value.trim() || null,
        is_anonymous: formIsAnonymous.value,
        ends_at: fromDatetimeLocal(formEndsAt.value),
        options: opts,
      };
      await guildsApi.updateGuildPoll(guildId.value, formPollId.value!, payload);
    }
    closeModal();
    await loadPolls();
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    formError.value = err?.message ?? 'Ошибка сохранения.';
  } finally {
    formSubmitting.value = false;
  }
}

// Удаление
const deleteDialogOpen = ref(false);
const deletePollId = ref<number | null>(null);
const deleteLoading = ref(false);

function openDelete(poll: GuildPollItem) {
  deletePollId.value = poll.id;
  deleteDialogOpen.value = true;
}

async function confirmDelete() {
  if (deletePollId.value == null) return;
  deleteLoading.value = true;
  try {
    await guildsApi.deleteGuildPoll(guildId.value, deletePollId.value);
    deleteDialogOpen.value = false;
    deletePollId.value = null;
    await loadPolls();
  } finally {
    deleteLoading.value = false;
  }
}

// Закрытие
const closeDialogOpen = ref(false);
const closePollId = ref<number | null>(null);
const closeLoading = ref(false);

function openClose(poll: GuildPollItem) {
  closePollId.value = poll.id;
  closeDialogOpen.value = true;
}

async function confirmClose() {
  if (closePollId.value == null) return;
  closeLoading.value = true;
  try {
    const updated = await guildsApi.closeGuildPoll(guildId.value, closePollId.value);
    const idx = polls.value.findIndex((p) => p.id === closePollId.value);
    if (idx !== -1) polls.value[idx] = updated;
    siteContext.triggerPollsRefresh();
    closeDialogOpen.value = false;
    closePollId.value = null;
  } finally {
    closeLoading.value = false;
  }
}

// Сброс
const resetDialogOpen = ref(false);
const resetPollId = ref<number | null>(null);
const resetLoading = ref(false);

function openReset(poll: GuildPollItem) {
  resetPollId.value = poll.id;
  resetDialogOpen.value = true;
}

async function confirmReset() {
  if (resetPollId.value == null) return;
  resetLoading.value = true;
  try {
    const updated = await guildsApi.resetGuildPoll(guildId.value, resetPollId.value);
    const idx = polls.value.findIndex((p) => p.id === resetPollId.value);
    if (idx !== -1) polls.value[idx] = updated;
    siteContext.triggerPollsRefresh();
    resetDialogOpen.value = false;
    resetPollId.value = null;
  } finally {
    resetLoading.value = false;
  }
}

// Голосование: один голос на пользователя, засчитывается при выборе, можно менять до закрытия
const voteCharacterId = ref<number | null>(null);
const voteOptionIdByPoll = ref<Record<number, number | null>>({});
const voteLoadingByPoll = ref<Record<number, boolean>>({});

function getVoteCharacterId(): number | null {
  const chars = myCharacters.value;
  if (chars.length === 0) return null;
  if (chars.length === 1) return chars[0].id;
  return voteCharacterId.value;
}

async function selectOptionAndVote(poll: GuildPollItem, optionId: number | null) {
  const charId = getVoteCharacterId();
  if (charId == null) return;
  const prevOptionId = voteOptionIdByPoll.value[poll.id];
  if (prevOptionId === optionId) return;

  voteOptionIdByPoll.value = { ...voteOptionIdByPoll.value, [poll.id]: optionId };
  voteLoadingByPoll.value[poll.id] = true;
  try {
    if (optionId == null) {
      await guildsApi.withdrawGuildPollVote(guildId.value, poll.id, charId);
    } else {
      await guildsApi.voteGuildPoll(guildId.value, poll.id, optionId, charId);
    }
    const updated = await guildsApi.getGuildPoll(guildId.value, poll.id);
    const idx = polls.value.findIndex((p) => p.id === poll.id);
    if (idx !== -1) polls.value[idx] = updated;
    siteContext.triggerPollsRefresh();
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err?.message ?? 'Ошибка голосования';
    voteOptionIdByPoll.value = { ...voteOptionIdByPoll.value, [poll.id]: prevOptionId };
  } finally {
    voteLoadingByPoll.value[poll.id] = false;
  }
}

function optionVotePercent(option: GuildPollOptionItem, total: number): number {
  if (total === 0) return 0;
  return Math.round((option.votes_count / total) * 100);
}

async function loadPolls() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  try {
    polls.value = await guildsApi.getGuildPolls(guildId.value);
  } catch (e) {
    polls.value = [];
    const st = (e as ApiError)?.status;
    if (st === 403 || st === 404) {
      guildPollsAccessNotFound.value = true;
    }
  }
}

async function loadPollsPage() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;

  guild.value = null;
  polls.value = [];
  loading.value = true;
  guildPollsAccessNotFound.value = false;
  error.value = null;

  try {
    guild.value = await guildsApi.getGuildForSettings(guildId.value);
  } catch (e: unknown) {
    const err = e as ApiError;
    if (err.status === 403 || err.status === 404) {
      guildPollsAccessNotFound.value = true;
    }
    guild.value = null;
    loading.value = false;
    return;
  }
  await loadPolls();
  loading.value = false;
}

watch(guildId, () => {
  loadPollsPage();
}, { immediate: true });

watch(
  () => guild.value?.my_characters,
  (chars) => {
    voteCharacterId.value = chars?.length && chars.length > 1 ? chars[0].id : null;
  },
  { immediate: true }
);

watch(
  polls,
  (newPolls) => {
    const next: Record<number, number | null> = { ...voteOptionIdByPoll.value };
    for (const poll of newPolls) {
      next[poll.id] = poll.my_vote_option_id ?? null;
    }
    voteOptionIdByPoll.value = next;
  },
  { deep: true, immediate: true }
);

async function refetchPollFromSocket(pollId: number) {
  const gid = guildId.value;
  if (!Number.isFinite(gid) || gid <= 0) return;
  try {
    const updated = await guildsApi.getGuildPoll(gid, pollId);
    const idx = polls.value.findIndex((p) => p.id === pollId);
    if (idx !== -1) {
      polls.value[idx] = updated;
    } else {
      polls.value = [updated, ...polls.value];
    }
  } catch {
    // ignore
  }
}

useGuildPollsSocket({
  guildIds: guildIdsForSocket,
  onChanged: ({ guildId: gid, pollId }) => {
    if (gid !== guildId.value) return;
    refetchPollFromSocket(pollId);
  },
  onDeleted: ({ guildId: gid, pollId }) => {
    if (gid !== guildId.value) return;
    polls.value = polls.value.filter((p) => p.id !== pollId);
  },
});
</script>

<template>
  <NotFoundPage v-if="guildPollsAccessNotFound" />
  <div v-else class="container py-4 md:py-6 max-w-2xl mx-auto">

    <div class="flex pb-4 justify-between items-center">
      <div class="text-xl font-semibold pb-4">Голосования</div>
      <Button
        v-if="canAdd"
        type="button"
        @click="openCreate"
      >
        Создать голосование
      </Button>
    </div>

    <div>
      <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>

      <template v-else-if="error">
        <p class="text-sm text-destructive">{{ error }}</p>
      </template>

      <template v-else-if="polls.length === 0 && guild">
        <p class="text-sm text-muted-foreground">
          Пока нет голосований.
          <template v-if="canAdd"> Нажмите «Создать голосование», чтобы создать первое.</template>
        </p>
      </template>

      <div v-else class="space-y-6">
        <Card
          v-for="poll in polls"
          :key="poll.id"
          class="overflow-hidden"
        >
          <CardHeader class="pb-2">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <CardTitle class="text-base">{{ poll.title }}</CardTitle>
                <p
                  v-if="poll.description"
                  class="mt-1 text-sm text-muted-foreground"
                >
                  {{ poll.description }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                  {{ poll.creator_character?.name ? `Создано: ${poll.creator_character.name}` : '' }}
                  <span v-if="poll.ends_at && !poll.is_closed" class="ml-2">
                    Окончание: {{ formatEndsAt(poll.ends_at) }}
                  </span>
                  <span v-if="poll.is_closed" class="ml-2 text-amber-600">Закрыто</span>
                  <span v-if="!poll.is_anonymous" class="ml-2 text-muted-foreground">Открытое</span>
                </p>
              </div>
              <div v-if="canEdit || canClose || canReset || canDelete" class="flex flex-wrap gap-1">
                <Button
                  v-if="canEdit && !poll.is_closed"
                  variant="outline"
                  size="sm"
                  @click="openEdit(poll)"
                >
                  Редактировать
                </Button>
                <Button
                  v-if="canClose && !poll.is_closed"
                  variant="outline"
                  size="sm"
                  @click="openClose(poll)"
                >
                  Закрыть
                </Button>
                <Button
                  v-if="canReset"
                  variant="outline"
                  size="sm"
                  @click="openReset(poll)"
                >
                  Сбросить
                </Button>
                <Button
                  v-if="canDelete"
                  variant="outline"
                  size="sm"
                  class="text-destructive hover:text-destructive"
                  @click="openDelete(poll)"
                >
                  Удалить
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent class="space-y-3">
            <div
              v-if="!poll.is_closed && myCharacters.length > 1"
              class="flex items-center gap-2 pb-2"
            >
              <span class="text-sm text-muted-foreground">Голосовать от имени:</span>
              <SelectRoot
                :model-value="voteCharacterId != null ? String(voteCharacterId) : ''"
                @update:model-value="(v) => { voteCharacterId = v ? Number(v) : null; }"
                class="w-48"
              >
                <SelectTrigger class="h-8 text-sm">
                  <SelectValue placeholder="Выберите персонажа" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="c in myCharacters"
                    :key="c.id"
                    :value="String(c.id)"
                  >
                    {{ c.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
            </div>
            <div
              v-for="opt in poll.options"
              :key="opt.id"
              class="space-y-1"
            >
              <button
                v-if="!poll.is_closed && myCharacters.length > 0"
                type="button"
                class="flex w-full cursor-pointer items-center gap-2 rounded-md border px-2 py-1.5 text-left text-sm transition-colors hover:bg-muted disabled:opacity-70"
                :class="voteOptionIdByPoll[poll.id] === opt.id ? 'border-primary bg-primary/10' : 'border-border'"
                :disabled="voteLoadingByPoll[poll.id]"
                @click="selectOptionAndVote(poll, voteOptionIdByPoll[poll.id] === opt.id ? null : opt.id)"
              >
                <span
                  class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                  :class="voteOptionIdByPoll[poll.id] === opt.id ? 'border-primary bg-primary' : 'border-muted-foreground'"
                >
                  <span v-if="voteOptionIdByPoll[poll.id] === opt.id" class="h-2 w-2 rounded-full bg-primary-foreground" />
                </span>
                <span class="min-w-0 flex-1">{{ opt.text }}</span>
                <span class="shrink-0 text-muted-foreground">{{ opt.votes_count }} ({{ optionVotePercent(opt, poll.total_votes) }}%)</span>
              </button>
              <template v-else>
                <div class="flex items-center justify-between gap-2 text-sm">
                  <span>{{ opt.text }}</span>
                  <span class="text-muted-foreground">{{ opt.votes_count }} ({{ optionVotePercent(opt, poll.total_votes) }}%)</span>
                </div>
              </template>
              <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full bg-primary transition-all"
                  :style="{ width: `${optionVotePercent(opt, poll.total_votes)}%` }"
                />
              </div>
              <p
              v-if="!poll.is_anonymous && opt.voters?.length"
              class="text-xs text-muted-foreground"
            >
              Проголосовали: {{ opt.voters.map((v) => v.name).join(', ') }}
            </p>
            </div>
            <p v-if="poll.total_votes > 0" class="pt-1 text-xs text-muted-foreground">
              Всего голосов: {{ poll.total_votes }}
            </p>
          </CardContent>
        </Card>
      </div>
    </div>


    <!-- Модальное окно создания/редактирования -->
    <DialogRoot v-model:open="modalOpen">
      <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-[3] bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 cursor-pointer"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-[4] w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 focus:outline-none max-h-[90vh] overflow-y-auto"
          :aria-describedby="undefined"
          @pointer-down-outside="closeModal"
        >
          <div class="relative">
            <button
              type="button"
              class="absolute right-0 top-0 z-10 rounded-sm p-1 opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
              aria-label="Закрыть"
              @click="closeModal"
            >
              <span class="text-2xl leading-none">×</span>
            </button>
            <DialogTitle class="pr-8 text-lg font-semibold">
              {{ formMode === 'create' ? 'Новое голосование' : 'Редактировать голосование' }}
            </DialogTitle>
            <form class="flex flex-col gap-4 pt-2" @submit.prevent="submitForm">
              <div class="space-y-2">
                <Label for="poll-title">Название <span class="text-destructive">*</span></Label>
                <Input
                  id="poll-title"
                  v-model="formTitle"
                  type="text"
                  placeholder="Вопрос голосования"
                  maxlength="255"
                  class="w-full"
                />
              </div>
              <div class="space-y-2">
                <Label for="poll-desc">Описание</Label>
                <textarea
                  id="poll-desc"
                  v-model="formDescription"
                  class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                  placeholder="Необязательно"
                  rows="2"
                />
              </div>
              <div class="flex items-center gap-2">
                <input
                  id="poll-anonymous"
                  v-model="formIsAnonymous"
                  type="checkbox"
                  class="h-4 w-4 rounded border-input"
                />
                <Label for="poll-anonymous" class="cursor-pointer font-normal">
                  Анонимное голосование
                </Label>
              </div>
              <p class="text-xs text-muted-foreground -mt-2">
                Если отключено, участники смогут видеть, кто за что проголосовал.
              </p>
              <div class="space-y-2">
                <Label for="poll-ends-at">Дата и время окончания</Label>
                <input
                  id="poll-ends-at"
                  v-model="formEndsAt"
                  type="datetime-local"
                  :min="datetimeLocalMin"
                  class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                />
                <p class="text-xs text-muted-foreground">
                  Необязательно. Оставьте пустым для голосования без срока.
                </p>
              </div>
              <div v-if="formMode === 'create' && myCharacters.length > 1" class="space-y-2">
                <Label>От имени персонажа</Label>
                <SelectRoot v-model="formCharacterId">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Выберите персонажа" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="SELECT_NONE">— Не указывать —</SelectItem>
                    <SelectItem
                      v-for="c in myCharacters"
                      :key="c.id"
                      :value="String(c.id)"
                    >
                      {{ c.name }}
                    </SelectItem>
                  </SelectContent>
                </SelectRoot>
              </div>
              <div class="space-y-2">
                <Label>Варианты ответа <span class="text-destructive">*</span> (минимум 2)</Label>
                <div
                  v-for="(opt, idx) in formOptions"
                  :key="idx"
                  class="flex gap-2"
                >
                  <Input
                    v-model="formOptions[idx]"
                    type="text"
                    placeholder="Вариант ответа"
                    maxlength="255"
                    class="flex-1"
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    :disabled="formOptions.length <= 2"
                    aria-label="Удалить вариант"
                    @click="removeFormOption(idx)"
                  >
                    ×
                  </Button>
                </div>
                <Button
                  v-if="formOptions.length < 20"
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="addFormOption"
                >
                  + Добавить вариант
                </Button>
              </div>
              <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>
              <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" :disabled="formSubmitting" @click="closeModal">
                  Отмена
                </Button>
                <Button type="submit" :disabled="formSubmitting">
                  {{ formSubmitting ? 'Сохранение…' : (formMode === 'create' ? 'Создать' : 'Сохранить') }}
                </Button>
              </div>
            </form>
          </div>
        </DialogContent>
      </DialogPortal>
      </ClientOnly>
    </DialogRoot>

    <ConfirmDialog
      v-model:open="deleteDialogOpen"
      title="Удалить голосование?"
      description="Это действие нельзя отменить."
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="deleteLoading"
      confirm-variant="destructive"
      @confirm="confirmDelete"
    />
    <ConfirmDialog
      v-model:open="closeDialogOpen"
      title="Закрыть голосование?"
      description="После закрытия новые голоса принимать не будут."
      confirm-label="Закрыть"
      cancel-label="Отмена"
      :loading="closeLoading"
      @confirm="confirmClose"
    />
    <ConfirmDialog
      v-model:open="resetDialogOpen"
      title="Сбросить голосование?"
      description="Все голоса будут удалены. Участники смогут проголосовать снова."
      confirm-label="Сбросить"
      cancel-label="Отмена"
      :loading="resetLoading"
      confirm-variant="destructive"
      @confirm="confirmReset"
    />
  </div>
</template>
