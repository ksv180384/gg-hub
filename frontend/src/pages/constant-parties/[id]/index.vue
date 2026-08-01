<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import type { DateRange } from 'radix-vue';
import {
  Badge,
  DateRangePicker,
  Select,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  type SelectOption,
} from '@/shared/ui';
import type { Character } from '@/shared/api/charactersApi';
import {
  constantPartiesApi,
  type ConstantParty,
  type ConstantPartyChatMessage,
  type ConstantPartyFormerMember,
  type ConstantPartyInvitation,
  type ConstantPartyStorageGrant,
  type ConstantPartyStorageItem,
} from '@/shared/api/constantPartiesApi';
import { useSiteContextStore } from '@/stores/siteContext';
import InviteCharacterDialog from './InviteCharacterDialog.vue';

const route = useRoute();
const siteContext = useSiteContextStore();
const partyId = computed(() => Number(route.params.id));
const party = ref<ConstantParty | null>(null);
const invitations = ref<ConstantPartyInvitation[]>([]);
const messages = ref<ConstantPartyChatMessage[]>([]);
const storageItems = ref<ConstantPartyStorageItem[]>([]);
const formerMembers = ref<ConstantPartyFormerMember[]>([]);
const selectedHistoryCharacterId = ref<number | null>(null);
const selectedHistoryCharacterName = ref('');
const characterGrants = ref<ConstantPartyStorageGrant[]>([]);
const loadingCharacterGrants = ref(false);
const loading = ref(true);
const error = ref<string | null>(null);
const activeTab = ref<'members' | 'chat' | 'storage' | 'invitations'>('members');
const inviteQuery = ref('');
const inviteCandidates = ref<Character[]>([]);
const searchingInviteCandidates = ref(false);
const invitingCharacterId = ref<number | null>(null);
const inviteMessage = ref('');
let inviteSearchTimer: ReturnType<typeof setTimeout> | null = null;
let inviteSearchRequestId = 0;
let historyRequestId = 0;
const chatBody = ref('');
const storageContext = ref({ can_manage_storage: false, my_member_id: 0, my_character_id: 0 });
const newItemName = ref('');
const newItemQuantity = ref<number | null>(null);
const grantItemId = ref<number | null>(null);
const grantCharacterId = ref<number | null>(null);
const grantReason = ref('');
const rosterFilter = ref<'all' | 'active' | 'inactive'>('active');
const historySearch = ref('');
const historyDateRange = shallowRef<DateRange>({
  start: undefined,
  end: undefined,
});
const historyDateFrom = computed(() => historyDateRange.value.start?.toString() ?? '');
const historyDateTo = computed(() => historyDateRange.value.end?.toString() ?? '');
const visibleGrantCount = ref(5);
type InvitationStatusFilter = 'all' | ConstantPartyInvitation['status'];

const inviteDialogOpen = ref(false);
const invitationStatusFilter = ref<InvitationStatusFilter>('all');

type RosterCharacter = {
  key: string;
  characterId: number;
  name: string;
  avatarUrl: string | null;
  isActive: boolean;
  role: 'leader' | 'member';
  canManageStorage: boolean;
  memberId: number | null;
  leftAt: string | null;
};

const myMember = computed(() => party.value?.my_member ?? null);
const canManageStorage = computed(() => storageContext.value.can_manage_storage || myMember.value?.role === 'leader');
const myCharacterId = computed(() => storageContext.value.my_character_id || myMember.value?.character_id || null);
const members = computed(() => party.value?.members ?? []);
const rosterCharacters = computed<RosterCharacter[]>(() => [
  ...members.value.map((member) => ({
    key: 'active-' + member.id,
    characterId: member.character_id,
    name: member.character?.name ?? 'Персонаж',
    avatarUrl: member.character?.avatar_url ?? null,
    isActive: true,
    role: member.role,
    canManageStorage: member.can_manage_storage,
    memberId: member.id,
    leftAt: null,
  })),
  ...formerMembers.value.map((member) => ({
    key: 'inactive-' + member.id,
    characterId: member.character_id,
    name: member.character?.name ?? 'Персонаж',
    avatarUrl: member.character?.avatar_url ?? null,
    isActive: false,
    role: 'member' as const,
    canManageStorage: false,
    memberId: null,
    leftAt: member.left_at,
  })),
]);
const filteredRosterCharacters = computed(() => rosterCharacters.value.filter((character) => {
  if (rosterFilter.value === 'active') return character.isActive;
  if (rosterFilter.value === 'inactive') return !character.isActive;
  return true;
}));
const selectedRosterCharacter = computed(() => rosterCharacters.value.find(
  (character) => character.characterId === selectedHistoryCharacterId.value,
) ?? null);
const filteredCharacterGrants = computed(() => {
  const search = historySearch.value.trim().toLowerCase();
  const from = historyDateFrom.value
    ? new Date(historyDateFrom.value + 'T00:00:00').getTime()
    : null;
  const to = historyDateTo.value
    ? new Date(historyDateTo.value + 'T23:59:59.999').getTime()
    : null;

  return characterGrants.value.filter((grant) => {
    const grantedAt = grant.granted_at ? new Date(grant.granted_at).getTime() : null;
    if (search && !grant.item?.name.toLowerCase().includes(search)) return false;
    if (from !== null && (grantedAt === null || grantedAt < from)) return false;
    if (to !== null && (grantedAt === null || grantedAt > to)) return false;
    return true;
  });
});
const visibleCharacterGrants = computed(() => filteredCharacterGrants.value.slice(0, visibleGrantCount.value));
const invitationStatusOptions: SelectOption[] = [
  { value: 'all', label: 'Все статусы' },
  { value: 'pending', label: 'Ожидает ответа' },
  { value: 'accepted', label: 'Принято' },
  { value: 'declined', label: 'Отклонено' },
  { value: 'revoked', label: 'Отозвано' },
  { value: 'expired', label: 'Истекло' },
];
const filteredInvitations = computed(() => invitations.value.filter((invitation) => (
  invitationStatusFilter.value === 'all'
  || invitation.status === invitationStatusFilter.value
)));

function invitationStatusLabel(status: ConstantPartyInvitation['status']) {
  return invitationStatusOptions.find((option) => option.value === status)?.label ?? status;
}

function invitationStatusClass(status: ConstantPartyInvitation['status']) {
  if (status === 'accepted') return 'border-emerald-600/30 text-emerald-700 dark:text-emerald-400';
  if (status === 'declined') return 'border-destructive/30 text-destructive';
  if (status === 'pending') return 'border-amber-600/30 text-amber-700 dark:text-amber-400';
  return 'text-muted-foreground';
}

async function load() {
  loading.value = true;
  error.value = null;
  try {
    if (!siteContext.game) {
      error.value = 'Конст пати доступны только на сайте выбранной игры.';
      return;
    }

    const [partyResult, contextResult] = await Promise.all([
      constantPartiesApi.get(partyId.value, siteContext.game.id),
      constantPartiesApi.storageContext(partyId.value),
    ]);
    party.value = partyResult;
    storageContext.value = contextResult;
    await Promise.all([
      loadInvitations(),
      loadMessages(),
      loadStorage(),
      loadFormerMembers(),
    ]);

    if (selectedHistoryCharacterId.value === null && rosterCharacters.value[0]) {
      await selectRosterCharacter(rosterCharacters.value[0]);
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить конст пати.';
  } finally {
    loading.value = false;
  }
}

async function loadInvitations() {
  invitations.value = await constantPartiesApi.listInvitations(partyId.value);
}

async function loadMessages() {
  messages.value = await constantPartiesApi.listMessages(partyId.value);
}

async function loadStorage() {
  storageItems.value = await constantPartiesApi.listStorageItems(partyId.value);
}

async function loadFormerMembers() {
  formerMembers.value = await constantPartiesApi.listFormerMembers(partyId.value);
}

async function selectRosterCharacter(character: RosterCharacter) {
  visibleGrantCount.value = 5;
  historySearch.value = '';
  historyDateRange.value = {
    start: undefined,
    end: undefined,
  };
  await showCharacterHistory(character.characterId, character.name);
}

async function showCharacterHistory(characterId: number, characterName: string) {
  const requestId = ++historyRequestId;
  selectedHistoryCharacterId.value = characterId;
  selectedHistoryCharacterName.value = characterName;
  loadingCharacterGrants.value = true;
  error.value = null;

  try {
    const grants = await constantPartiesApi.listCharacterGrants(
      partyId.value,
      characterId,
    );
    if (requestId === historyRequestId) {
      characterGrants.value = grants;
    }
  } catch (e) {
    if (requestId === historyRequestId) {
      error.value = e instanceof Error
        ? e.message
        : 'Не удалось загрузить историю полученных предметов.';
    }
  } finally {
    if (requestId === historyRequestId) {
      loadingCharacterGrants.value = false;
    }
  }
}

function setInviteDialogOpen(value: boolean) {
  inviteDialogOpen.value = value;
  if (value) return;

  if (inviteSearchTimer !== null) {
    clearTimeout(inviteSearchTimer);
    inviteSearchTimer = null;
  }
  inviteSearchRequestId += 1;
  inviteQuery.value = '';
  inviteCandidates.value = [];
  inviteMessage.value = '';
  searchingInviteCandidates.value = false;
}

async function searchInviteCandidates() {
  const query = inviteQuery.value.trim();
  inviteCandidates.value = [];
  const requestId = ++inviteSearchRequestId;
  if (query.length < 2) {
    searchingInviteCandidates.value = false;
    return;
  }
  searchingInviteCandidates.value = true;
  try {
    const result = await constantPartiesApi.searchInviteCandidates(partyId.value, query);
    if (requestId === inviteSearchRequestId) {
      inviteCandidates.value = result;
    }
  } catch (e) {
    if (requestId === inviteSearchRequestId) {
      error.value = e instanceof Error ? e.message : 'Не удалось найти персонажей.';
    }
  } finally {
    if (requestId === inviteSearchRequestId) {
      searchingInviteCandidates.value = false;
    }
  }
}

async function invite(character: Character) {
  invitingCharacterId.value = character.id;
  try {
    await constantPartiesApi.invite(partyId.value, {
      character_id: character.id,
      message: inviteMessage.value.trim() || null,
    });
    setInviteDialogOpen(false);
    await loadInvitations();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить приглашение.';
  } finally {
    invitingCharacterId.value = null;
  }
}

async function toggleStorageRight(memberId: number, value: boolean) {
  try {
    await constantPartiesApi.updateMember(partyId.value, memberId, { can_manage_storage: value });
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось обновить права.';
  }
}

async function removeMember(memberId: number) {
  try {
    await constantPartiesApi.deleteMember(partyId.value, memberId);
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось исключить участника.';
  }
}

async function sendMessage() {
  if (!myCharacterId.value || !chatBody.value.trim()) return;
  try {
    await constantPartiesApi.sendMessage(partyId.value, {
      character_id: myCharacterId.value,
      body: chatBody.value.trim(),
    });
    chatBody.value = '';
    await loadMessages();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить сообщение.';
  }
}

async function addStorageItem() {
  if (!canManageStorage.value || !myCharacterId.value || !newItemName.value.trim()) return;
  try {
    await constantPartiesApi.createStorageItem(partyId.value, {
      name: newItemName.value.trim(),
      quantity: newItemQuantity.value,
      actor_character_id: myCharacterId.value,
    });
    newItemName.value = '';
    newItemQuantity.value = null;
    await loadStorage();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось добавить предмет.';
  }
}

async function grantItem() {
  if (!canManageStorage.value || !myCharacterId.value || !grantItemId.value || !grantCharacterId.value) return;
  try {
    await constantPartiesApi.createGrant(partyId.value, {
      item_id: grantItemId.value,
      received_by_character_id: grantCharacterId.value,
      granted_by_character_id: myCharacterId.value,
      reason: grantReason.value.trim() || null,
    });
    grantReason.value = '';
    await loadStorage();
    if (selectedHistoryCharacterId.value === grantCharacterId.value) {
      await showCharacterHistory(
        grantCharacterId.value,
        selectedHistoryCharacterName.value,
      );
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось выдать предмет.';
  }
}

function characterInitials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('');
}

function characterAvatarColor(characterId: number) {
  const colors = ['#2563eb', '#7c3aed', '#0284c7', '#ea580c', '#16a34a', '#db2777', '#78716c'];
  return colors[characterId % colors.length];
}

function formatHistoryDate(value?: string | null) {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('ru-RU');
}

function formatHistoryTime(value?: string | null) {
  if (!value) return '';
  return new Date(value).toLocaleTimeString('ru-RU');
}

function formatDate(value?: string | null) {
  if (!value) return '';
  return new Date(value).toLocaleString('ru-RU');
}

onMounted(load);

watch(
  [historySearch, historyDateFrom, historyDateTo],
  () => {
    visibleGrantCount.value = 5;
  },
);

watch(inviteQuery, () => {
  if (inviteSearchTimer !== null) {
    clearTimeout(inviteSearchTimer);
  }
  inviteSearchTimer = setTimeout(() => {
    void searchInviteCandidates();
  }, 300);
});
</script>

<template>
  <div>
    <div class="mx-auto max-w-7xl">
      <div v-if="loading" class="flex justify-center py-10">
        <Spinner class="h-8 w-8" />
      </div>

      <template v-else-if="party">
        <div class="mb-5">
          <h1 class="text-2xl font-bold tracking-tight">{{ party.name }}</h1>
        </div>

        <div v-if="error" class="mb-4 rounded-md border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          {{ error }}
        </div>

        <div class="mb-4 flex gap-1 border-b">
          <button type="button" class="px-3 py-2 text-sm font-medium" :class="activeTab === 'members' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground'" @click="activeTab = 'members'">
            Состав
          </button>
          <button type="button" class="px-3 py-2 text-sm font-medium" :class="activeTab === 'chat' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground'" @click="activeTab = 'chat'">
            Чат
          </button>
          <button type="button" class="px-3 py-2 text-sm font-medium" :class="activeTab === 'storage' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground'" @click="activeTab = 'storage'">
            Хранилище
          </button>
          <button
            v-if="canManageStorage"
            type="button"
            class="px-3 py-2 text-sm font-medium"
            :class="
              activeTab === 'invitations'
                ? 'border-b-2 border-primary text-foreground'
                : 'text-muted-foreground'
            "
            @click="activeTab = 'invitations'"
          >
            Приглашения
          </button>
        </div>

        <section v-if="activeTab === 'members'" class="space-y-4">
          <div class="grid gap-4 lg:grid-cols-[22rem_minmax(0,1fr)]">
            <div class="flex min-h-[32rem] flex-col overflow-hidden rounded-lg border bg-background lg:h-[39.75rem]">
              <div class="flex items-center justify-between gap-3 px-4 pb-3 pt-4">
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-semibold">Персонажи</h2>
                  <span class="text-sm text-muted-foreground">{{ rosterCharacters.length }}</span>
                </div>
                <button
                  v-if="canManageStorage"
                  type="button"
                  class="h-8 rounded-md border border-emerald-700/20 bg-emerald-700/10 px-3 text-xs font-medium text-emerald-800 hover:bg-emerald-700/15 dark:border-emerald-300/20 dark:bg-emerald-300/10 dark:text-emerald-300 dark:hover:bg-emerald-300/15"
                  @click="setInviteDialogOpen(true)"
                >
                  + Пригласить
                </button>
              </div>

              <div class="grid grid-cols-3 gap-1 px-4 pb-3">
                <button
                  type="button"
                  class="h-9 rounded-md border px-2 text-xs font-medium"
                  :class="rosterFilter === 'active' ? 'border-primary bg-primary/5 text-primary' : 'text-muted-foreground hover:bg-muted'"
                  @click="rosterFilter = 'active'"
                >
                  Активные
                </button>
                <button
                  type="button"
                  class="h-9 rounded-md border px-2 text-xs font-medium"
                  :class="rosterFilter === 'inactive' ? 'border-primary bg-primary/5 text-primary' : 'text-muted-foreground hover:bg-muted'"
                  @click="rosterFilter = 'inactive'"
                >
                  Неактивные
                </button>
                <button
                  type="button"
                  class="h-9 rounded-md border px-2 text-xs font-medium"
                  :class="rosterFilter === 'all' ? 'border-primary bg-primary/5 text-primary' : 'text-muted-foreground hover:bg-muted'"
                  @click="rosterFilter = 'all'"
                >
                  Все
                </button>
              </div>

              <div class="min-h-0 flex-1 overflow-y-auto px-4">
                <p
                  v-if="filteredRosterCharacters.length === 0"
                  class="py-8 text-center text-sm text-muted-foreground"
                >
                  Персонажей в этой категории нет.
                </p>
                <button
                  v-for="character in filteredRosterCharacters"
                  :key="character.key"
                  type="button"
                  class="mb-1 flex w-full items-center gap-3 rounded-md border border-transparent px-2 py-2 text-left transition-colors hover:bg-muted/60"
                  :class="[
                    selectedHistoryCharacterId === character.characterId
                      ? 'border-primary bg-primary/5'
                      : '',
                    character.isActive ? '' : 'opacity-50',
                  ]"
                  @click="selectRosterCharacter(character)"
                >
                  <img
                    v-if="character.avatarUrl"
                    :src="character.avatarUrl"
                    :alt="character.name"
                    class="h-10 w-10 shrink-0 rounded-full object-cover"
                  />
                  <span
                    v-else
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-medium text-white"
                    :style="{ backgroundColor: characterAvatarColor(character.characterId) }"
                  >
                    {{ characterInitials(character.name) }}
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">{{ character.name }}</span>
                    <span class="mt-0.5 block truncate text-xs text-muted-foreground">
                      <template v-if="character.isActive">
                        {{ character.role === 'leader' ? 'Лидер' : 'Участник' }}
                        <span v-if="character.canManageStorage"> · Хранилище</span>
                      </template>
                      <template v-else>
                        Неактивен
                      </template>
                    </span>
                  </span>
                  <span class="text-xl leading-none text-muted-foreground">›</span>
                </button>
              </div>


            </div>

            <div class="min-w-0 space-y-4">
              <div
                v-if="selectedRosterCharacter"
                class="flex min-h-24 flex-wrap items-center gap-4 rounded-lg border bg-background px-5 py-4"
              >
                <img
                  v-if="selectedRosterCharacter.avatarUrl"
                  :src="selectedRosterCharacter.avatarUrl"
                  :alt="selectedRosterCharacter.name"
                  class="h-14 w-14 shrink-0 rounded-full object-cover"
                />
                <span
                  v-else
                  class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-base font-medium text-white"
                  :style="{ backgroundColor: characterAvatarColor(selectedRosterCharacter.characterId) }"
                >
                  {{ characterInitials(selectedRosterCharacter.name) }}
                </span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-lg font-semibold">{{ selectedRosterCharacter.name }}</p>
                  <p class="mt-1 text-sm text-muted-foreground">
                    <template v-if="selectedRosterCharacter.isActive">
                      {{ selectedRosterCharacter.role === 'leader' ? 'Лидер' : 'Участник' }}
                      <span v-if="selectedRosterCharacter.canManageStorage"> · Доступ к хранилищу</span>
                    </template>
                    <template v-else>
                      Неактивен · вышел {{ formatHistoryDate(selectedRosterCharacter.leftAt) }}
                    </template>
                  </p>
                </div>
                <div
                  v-if="
                    myMember?.role === 'leader'
                    && selectedRosterCharacter.isActive
                    && selectedRosterCharacter.role !== 'leader'
                    && selectedRosterCharacter.memberId !== null
                  "
                  class="flex flex-wrap items-center gap-3"
                >
                  <label class="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                      type="checkbox"
                      :checked="selectedRosterCharacter.canManageStorage"
                      @change="
                        toggleStorageRight(
                          selectedRosterCharacter.memberId!,
                          ($event.target as HTMLInputElement).checked,
                        )
                      "
                    />
                    Доступ к хранилищу
                  </label>
                  <button
                    type="button"
                    class="h-9 rounded-md border px-3 text-xs font-medium text-destructive hover:bg-destructive/5"
                    @click="removeMember(selectedRosterCharacter.memberId)"
                  >
                    Исключить
                  </button>
                </div>
              </div>

              <div class="rounded-lg border bg-background">
                <div class="px-5 pb-3 pt-5">
                  <h2 class="text-base font-semibold">История полученных предметов</h2>
                  <p class="mt-2 text-sm text-muted-foreground">
                    Всего предметов: {{ characterGrants.length }}
                  </p>
                </div>

                <div class="grid gap-2 px-5 pb-4 sm:grid-cols-[minmax(0,15rem)_minmax(0,1fr)]">
                  <DateRangePicker v-model="historyDateRange" />
                  <input
                    v-model="historySearch"
                    class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                    placeholder="Поиск предметов"
                  />
                </div>

                <div v-if="loadingCharacterGrants" class="flex justify-center py-14">
                  <Spinner class="h-7 w-7" />
                </div>
                <div
                  v-else-if="selectedHistoryCharacterId === null"
                  class="py-14 text-center text-sm text-muted-foreground"
                >
                  Выберите персонажа, чтобы посмотреть историю.
                </div>
                <div
                  v-else-if="filteredCharacterGrants.length === 0"
                  class="py-14 text-center text-sm text-muted-foreground"
                >
                  Полученных предметов не найдено.
                </div>
                <template v-else>
                  <div class="px-5">
                    <Table class="table-fixed">
                      <TableHeader>
                        <TableRow class="border-b text-xs text-muted-foreground">
                          <TableHead
                            class="h-auto px-1 py-3 font-medium normal-case tracking-normal"
                          >
                            Предмет
                          </TableHead>
                          <TableHead
                            class="h-auto w-[10%] px-1 py-3 font-medium normal-case tracking-normal"
                            title="Количество"
                          >
                            Кол-во
                          </TableHead>
                          <TableHead
                            class="h-auto w-[24%] px-1 py-3 font-medium normal-case tracking-normal"
                          >
                            Выдал
                          </TableHead>
                          <TableHead
                            class="h-auto w-28 px-1 py-3 font-medium normal-case tracking-normal"
                          >
                            Дата
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        <TableRow
                          v-for="grant in visibleCharacterGrants"
                          :key="grant.id"
                          class="border-b last:border-b-0"
                        >
                          <TableCell class="break-words px-1 py-4 align-top">
                            <p>{{ grant.item?.name ?? 'Предмет' }}</p>
                            <p v-if="grant.reason" class="mt-1 truncate text-xs text-muted-foreground">
                              {{ grant.reason }}
                            </p>
                          </TableCell>
                          <TableCell class="break-words px-1 py-4 align-top">1</TableCell>
                          <TableCell class="break-words px-1 py-4 align-top">
                            {{ grant.granted_by_character?.name ?? 'Персонаж' }}
                          </TableCell>
                          <TableCell class="break-words px-1 py-4 align-top">
                            <span class="block whitespace-nowrap">
                              {{ formatHistoryDate(grant.granted_at) }}
                            </span>
                            <span class="mt-1 block text-xs text-muted-foreground">
                              {{ formatHistoryTime(grant.granted_at) }}
                            </span>
                          </TableCell>
                        </TableRow>
                      </TableBody>
                    </Table>
                  </div>

                  <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-5">
                    <p class="text-xs text-muted-foreground">
                      Показано {{ visibleCharacterGrants.length }} из
                      {{ filteredCharacterGrants.length }} записей
                    </p>
                    <button
                      v-if="visibleCharacterGrants.length < filteredCharacterGrants.length"
                      type="button"
                      class="h-9 rounded-md border px-4 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                      @click="visibleGrantCount += 5"
                    >
                      Показать еще
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <InviteCharacterDialog
            :open="inviteDialogOpen"
            :query="inviteQuery"
            :message="inviteMessage"
            :candidates="inviteCandidates"
            :searching="searchingInviteCandidates"
            :inviting-character-id="invitingCharacterId"
            :server-name="party.server?.name"
            @update:open="setInviteDialogOpen"
            @update:query="inviteQuery = $event"
            @update:message="inviteMessage = $event"
            @invite="invite"
          />
        </section>

        <section
          v-else-if="activeTab === 'invitations'"
          class="space-y-4"
        >
          <div class="rounded-lg border bg-background">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
              <h2 class="text-sm font-semibold">Приглашения</h2>
              <Select
                v-model="invitationStatusFilter"
                :options="invitationStatusOptions"
                placeholder="Все статусы"
                trigger-class="h-9 w-48"
              />
            </div>

            <p
              v-if="invitations.length === 0"
              class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
              Приглашений пока нет.
            </p>
            <p
              v-else-if="filteredInvitations.length === 0"
              class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
              Приглашений с выбранным статусом нет.
            </p>
            <div v-else>
              <div
                v-for="invitation in filteredInvitations"
                :key="invitation.id"
                class="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium">
                    {{ invitation.invited_character?.name ?? 'Персонаж' }}
                  </p>
                  <p
                    v-if="invitation.created_at"
                    class="mt-1 text-xs text-muted-foreground"
                  >
                    {{ formatDate(invitation.created_at) }}
                  </p>
                </div>
                <Badge
                  variant="outline"
                  :class="invitationStatusClass(invitation.status)"
                >
                  {{ invitationStatusLabel(invitation.status) }}
                </Badge>
              </div>
            </div>
          </div>
        </section>

        <section v-else-if="activeTab === 'chat'" class="space-y-4">
          <div class="max-h-[28rem] overflow-y-auto rounded-lg border bg-background">
            <div v-if="messages.length === 0" class="p-6 text-center text-sm text-muted-foreground">Сообщений пока нет.</div>
            <div v-for="message in messages" :key="message.id" class="border-b p-4 last:border-b-0">
              <div class="mb-1 flex items-baseline justify-between gap-3">
                <p class="text-sm font-medium">{{ message.character?.name ?? 'Персонаж' }}</p>
                <span class="text-xs text-muted-foreground">{{ formatDate(message.created_at) }}</span>
              </div>
              <p class="whitespace-pre-wrap text-sm">{{ message.body }}</p>
            </div>
          </div>
          <form class="flex gap-2" @submit.prevent="sendMessage">
            <input v-model="chatBody" class="h-10 flex-1 rounded-md border bg-background px-3 text-sm" placeholder="Сообщение" />
            <button type="submit" class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Отправить</button>
          </form>
        </section>

        <section v-else class="grid gap-4 lg:grid-cols-[1fr_20rem]">
          <div class="overflow-hidden rounded-lg border bg-background">
            <div v-if="storageItems.length === 0" class="p-6 text-center text-sm text-muted-foreground">В хранилище пока нет предметов.</div>
            <div v-for="item in storageItems" :key="item.id" class="flex items-center justify-between gap-3 border-b p-4 last:border-b-0">
              <div>
                <p class="font-medium">{{ item.name }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                  Остаток: {{ item.quantity === null ? 'без ограничения' : item.quantity }} · выдач: {{ item.grants_count ?? 0 }}
                </p>
              </div>
            </div>
          </div>

          <aside v-if="canManageStorage" class="space-y-4">
            <form class="rounded-lg border bg-background p-4" @submit.prevent="addStorageItem">
              <h2 class="mb-3 text-sm font-semibold">Добавить предмет</h2>
              <input v-model="newItemName" class="mb-2 h-9 w-full rounded-md border bg-background px-3 text-sm" placeholder="Название" />
              <input v-model.number="newItemQuantity" class="mb-3 h-9 w-full rounded-md border bg-background px-3 text-sm" min="0" placeholder="Количество" type="number" />
              <button type="submit" class="h-9 w-full rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Добавить</button>
            </form>

            <form class="rounded-lg border bg-background p-4" @submit.prevent="grantItem">
              <h2 class="mb-3 text-sm font-semibold">Выдать предмет</h2>
              <select v-model.number="grantItemId" class="mb-2 h-9 w-full rounded-md border bg-background px-3 text-sm">
                <option :value="null">Предмет</option>
                <option v-for="item in storageItems" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
              <select v-model.number="grantCharacterId" class="mb-2 h-9 w-full rounded-md border bg-background px-3 text-sm">
                <option :value="null">Получатель</option>
                <option v-for="member in members" :key="member.id" :value="member.character_id">{{ member.character?.name }}</option>
              </select>
              <input v-model="grantReason" class="mb-3 h-9 w-full rounded-md border bg-background px-3 text-sm" placeholder="Причина" />
              <button type="submit" class="h-9 w-full rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground">Выдать</button>
            </form>
          </aside>
        </section>
      </template>

      <div v-else class="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        {{ error ?? 'Конст пати не найдена.' }}
      </div>
    </div>
  </div>
</template>
