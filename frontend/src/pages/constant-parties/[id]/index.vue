<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { DateRange } from 'radix-vue';
import {
  Crown,
  Gift,
  KeyRound,
  LogOut,
  MoreHorizontal,
  PackagePlus,
  Pencil,
  Trash2,
  UserMinus,
} from '@lucide/vue';
import {
  Badge,
  Button,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
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
  type ConstantPartyChatReceiptSummary,
  type ConstantPartyFormerMember,
  type ConstantPartyInvitation,
  type ConstantPartyStorageGrant,
  type ConstantPartyStorageItem,
  type ConstantPartyStorageLog,
} from '@/shared/api/constantPartiesApi';
import { useSiteContextStore } from '@/stores/siteContext';
import { useConstantPartyChatSocket } from '@/shared/lib/useConstantPartyChatSocket';
import BackIconButton from '@/shared/ui/back-icon-button/BackIconButton.vue';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import AddStorageItemDialog from './AddStorageItemDialog.vue';
import EditStorageItemDialog from './EditStorageItemDialog.vue';
import GrantStorageItemDialog from './GrantStorageItemDialog.vue';
import InviteCharacterDialog from './InviteCharacterDialog.vue';
import StorageLogsTab from './StorageLogsTab.vue';

const constantPartyChatEnabled = ['1', 'true', 'yes', 'on'].includes(
  String(import.meta.env.VITE_CONSTANT_PARTY_CHAT_ENABLED ?? '').toLowerCase(),
);
const constantPartyChatEnabledRef = computed(() => constantPartyChatEnabled);
const ConstantPartyChatTab = defineAsyncComponent(
  () => import('./ConstantPartyChatTab.vue'),
);
const route = useRoute();
const router = useRouter();
const siteContext = useSiteContextStore();
const partyId = computed(() => Number(route.params.id));
const party = ref<ConstantParty | null>(null);
const invitations = ref<ConstantPartyInvitation[]>([]);
const messages = ref<ConstantPartyChatMessage[]>([]);
const storageItems = ref<ConstantPartyStorageItem[]>([]);
const storageLogs = ref<ConstantPartyStorageLog[]>([]);
const storageLogsLoading = ref(false);
const storageLogsPage = ref(1);
const storageLogsLastPage = ref(1);
const storageLogsSort = ref<'asc' | 'desc'>('desc');
const storageLogsDateRange = shallowRef<DateRange>({
  start: undefined,
  end: undefined,
});
const addStorageItemDialogOpen = ref(false);
const addStorageItemSaving = ref(false);
const grantStorageItemDialogOpen = ref(false);
const grantStorageItemSaving = ref(false);
const editingStorageItem = ref<ConstantPartyStorageItem | null>(null);
const storageItemSaving = ref(false);
const formerMembers = ref<ConstantPartyFormerMember[]>([]);
const selectedHistoryCharacterId = ref<number | null>(null);
const selectedHistoryCharacterName = ref('');
const characterGrants = ref<ConstantPartyStorageGrant[]>([]);
const loadingCharacterGrants = ref(false);
const loading = ref(true);
const error = ref<string | null>(null);
const activeTab = ref<'members' | 'chat' | 'storage' | 'logs' | 'invitations'>('members');
const inviteQuery = ref('');
const inviteCandidates = ref<Character[]>([]);
const searchingInviteCandidates = ref(false);
const invitingCharacterId = ref<number | null>(null);
const inviteMessage = ref('');
let inviteSearchTimer: ReturnType<typeof setTimeout> | null = null;
let inviteSearchRequestId = 0;
let historyRequestId = 0;
let storageLogsRequestId = 0;
const chatBody = ref('');
const chatLoading = ref(false);
const chatSending = ref(false);
const storageContext = ref({ can_manage_storage: false, my_member_id: 0, my_character_id: 0 });
const rosterFilter = ref<'all' | 'active' | 'inactive'>('active');
const historySearch = ref('');
const historyDateRange = shallowRef<DateRange>({
  start: undefined,
  end: undefined,
});
const historyDateFrom = computed(() => historyDateRange.value.start?.toString() ?? '');
const historyDateTo = computed(() => historyDateRange.value.end?.toString() ?? '');
const storageLogsDateFrom = computed(() => storageLogsDateRange.value.start?.toString() ?? '');
const storageLogsDateTo = computed(() => storageLogsDateRange.value.end?.toString() ?? '');
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

const memberActionLoading = ref(false);
const transferLeadershipTarget = ref<RosterCharacter | null>(null);
const removeMemberTarget = ref<RosterCharacter | null>(null);
const leavePartyDialogOpen = ref(false);
const dissolvePartyDialogOpen = ref(false);
const dissolvePartyLoading = ref(false);

const myMember = computed(() => party.value?.my_member ?? null);
const canManageStorage = computed(() => storageContext.value.can_manage_storage || myMember.value?.role === 'leader');
const myCharacterId = computed(() => storageContext.value.my_character_id || myMember.value?.character_id || null);
const isCurrentUserLeader = computed(() => (
  party.value?.leader_character_id === myCharacterId.value
));
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
const totalCharacterGrantQuantity = computed(() => characterGrants.value.reduce(
  (total, grant) => total + grant.quantity,
  0,
));
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

const {
  connected: chatSocketConnected,
  authenticated: chatSocketAuthenticated,
  onlineCharacters: chatOnlineCharacters,
} = useConstantPartyChatSocket({
  enabled: constantPartyChatEnabledRef,
  partyId,
  characterId: myCharacterId,
  getToken: getChatSocketToken,
  onMessageCreated: onSocketMessageCreated,
  onReceiptsChanged: applyReceiptSummaries,
  onMessageDeleted: onSocketMessageDeleted,
});

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
  if (!constantPartyChatEnabled) return;

  chatLoading.value = true;
  try {
    messages.value = await constantPartiesApi.listMessages(partyId.value);
    void acknowledgeIncomingMessages(false);
  } finally {
    chatLoading.value = false;
  }
}

async function loadStorage() {
  storageItems.value = await constantPartiesApi.listStorageItems(partyId.value);
}

async function loadStorageLogs(page = 1) {
  const requestId = ++storageLogsRequestId;
  storageLogsLoading.value = true;
  error.value = null;
  try {
    const result = await constantPartiesApi.listStorageLogs(partyId.value, {
      page,
      dateFrom: storageLogsDateFrom.value || undefined,
      dateTo: storageLogsDateTo.value || undefined,
      sort: storageLogsSort.value,
    });
    if (requestId === storageLogsRequestId) {
      storageLogs.value = result.logs;
      storageLogsPage.value = result.currentPage;
      storageLogsLastPage.value = result.lastPage;
    }
  } catch (e) {
    if (requestId === storageLogsRequestId) {
      error.value = e instanceof Error ? e.message : 'Не удалось загрузить журнал КП.';
    }
  } finally {
    if (requestId === storageLogsRequestId) {
      storageLogsLoading.value = false;
    }
  }
}

function invalidateStorageLogs() {
  storageLogsRequestId += 1;
  storageLogs.value = [];
  storageLogsPage.value = 1;
  storageLogsLastPage.value = 1;
  storageLogsLoading.value = false;
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
  memberActionLoading.value = true;
  error.value = null;
  try {
    const updatedMember = await constantPartiesApi.updateMember(
      partyId.value,
      memberId,
      { can_manage_storage: value },
    );
    const currentMember = party.value?.members?.find((member) => member.id === memberId);
    if (currentMember) {
      currentMember.can_manage_storage = updatedMember.can_manage_storage;
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось обновить права.';
  } finally {
    memberActionLoading.value = false;
  }
}

function openTransferLeadershipDialog(character: RosterCharacter) {
  transferLeadershipTarget.value = character;
}

async function confirmTransferLeadership() {
  const target = transferLeadershipTarget.value;
  if (target?.memberId === null || target?.memberId === undefined) return;

  memberActionLoading.value = true;
  error.value = null;
  try {
    await constantPartiesApi.transferLeadership(partyId.value, target.memberId);
    transferLeadershipTarget.value = null;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось передать лидерство.';
  } finally {
    memberActionLoading.value = false;
  }
}

function openRemoveMemberDialog(character: RosterCharacter) {
  removeMemberTarget.value = character;
}

async function confirmRemoveMember() {
  const target = removeMemberTarget.value;
  if (target?.memberId === null || target?.memberId === undefined) return;

  memberActionLoading.value = true;
  error.value = null;
  try {
    await constantPartiesApi.deleteMember(partyId.value, target.memberId);
    removeMemberTarget.value = null;
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось исключить участника.';
  } finally {
    memberActionLoading.value = false;
  }
}

async function confirmLeaveParty() {
  if (!myMember.value || myMember.value.role === 'leader') return;

  memberActionLoading.value = true;
  error.value = null;
  try {
    await constantPartiesApi.deleteMember(partyId.value, myMember.value.id);
    leavePartyDialogOpen.value = false;
    await router.push('/my-constant-parties');
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось покинуть КП.';
  } finally {
    memberActionLoading.value = false;
  }
}


async function confirmDissolveParty() {
  if (!isCurrentUserLeader.value) return;

  dissolvePartyLoading.value = true;
  error.value = null;
  try {
    await constantPartiesApi.deleteParty(partyId.value);
    dissolvePartyDialogOpen.value = false;
    await router.push('/my-constant-parties');
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось распустить КП.';
  } finally {
    dissolvePartyLoading.value = false;
  }
}

async function getChatSocketToken(): Promise<string> {
  if (!myCharacterId.value) {
    throw new Error('Персонаж КП не выбран.');
  }

  const result = await constantPartiesApi.createChatSocketToken(
    partyId.value,
    myCharacterId.value,
  );
  return result.token;
}

function upsertChatMessage(message: ConstantPartyChatMessage) {
  const index = messages.value.findIndex((item) => item.id === message.id);
  if (index === -1) {
    messages.value = [...messages.value, message].sort((left, right) => (
      new Date(left.created_at).getTime() - new Date(right.created_at).getTime()
    ));
    return;
  }

  messages.value[index] = {
    ...messages.value[index],
    ...message,
  };
}

function applyReceiptSummaries(summaries: ConstantPartyChatReceiptSummary[]) {
  for (const summary of summaries) {
    const message = messages.value.find((item) => item.id === summary.id);
    if (!message) continue;
    Object.assign(message, summary);
  }
}

function chatIsReadable(): boolean {
  return activeTab.value === 'chat'
    && (typeof document === 'undefined' || document.visibilityState === 'visible');
}

async function acknowledgeIncomingMessages(
  markRead: boolean,
  messageIds?: number[],
) {
  if (!myCharacterId.value) return;

  const allowedIds = messageIds ? new Set(messageIds) : null;
  const incomingIds = messages.value
    .filter((message) => (
      message.character_id !== myCharacterId.value
      && (!allowedIds || allowedIds.has(message.id))
    ))
    .map((message) => message.id)
    .slice(-100);
  if (incomingIds.length === 0) return;

  try {
    const updated = markRead
      ? await constantPartiesApi.markChatMessagesRead(
          partyId.value,
          myCharacterId.value,
          incomingIds,
        )
      : await constantPartiesApi.markChatMessagesDelivered(
          partyId.value,
          myCharacterId.value,
          incomingIds,
        );
    for (const message of updated) {
      upsertChatMessage(message);
    }
  } catch {
    // Receipts are best-effort and will be retried when the chat becomes visible.
  }
}

function onSocketMessageCreated(message: ConstantPartyChatMessage) {
  upsertChatMessage(message);
  if (message.character_id !== myCharacterId.value) {
    void acknowledgeIncomingMessages(chatIsReadable(), [message.id]);
  }
}

function onSocketMessageDeleted(messageId: number) {
  messages.value = messages.value.filter((message) => message.id !== messageId);
}

function onChatVisibilityChanged() {
  if (chatIsReadable()) {
    void acknowledgeIncomingMessages(true);
  }
}

async function sendMessage() {
  if (!myCharacterId.value || !chatBody.value.trim() || chatSending.value) return;

  chatSending.value = true;
  error.value = null;
  try {
    const message = await constantPartiesApi.sendMessage(partyId.value, {
      character_id: myCharacterId.value,
      body: chatBody.value.trim(),
    });
    upsertChatMessage(message);
    chatBody.value = '';
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить сообщение.';
  } finally {
    chatSending.value = false;
  }
}

async function addStorageItem(payload: { name: string; quantity: number | null }) {
  if (!canManageStorage.value || !myCharacterId.value) return;

  addStorageItemSaving.value = true;
  error.value = null;
  try {
    await constantPartiesApi.createStorageItem(partyId.value, {
      name: payload.name,
      quantity: payload.quantity,
      actor_character_id: myCharacterId.value,
    });
    addStorageItemDialogOpen.value = false;
    invalidateStorageLogs();
    await loadStorage();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось добавить предмет.';
  } finally {
    addStorageItemSaving.value = false;
  }
}

function openStorageItemEditor(item: ConstantPartyStorageItem) {
  editingStorageItem.value = item;
}

async function saveStorageItem(payload: { name: string; quantity: number | null }) {
  const item = editingStorageItem.value;
  if (!item || !myCharacterId.value) return;

  storageItemSaving.value = true;
  error.value = null;
  try {
    const updatedItem = await constantPartiesApi.updateStorageItem(
      partyId.value,
      item.id,
      {
        name: payload.name,
        description: item.description,
        quantity: payload.quantity,
        tier_id: item.tier_id,
        actor_character_id: myCharacterId.value,
      },
    );
    const itemIndex = storageItems.value.findIndex((currentItem) => currentItem.id === item.id);
    if (itemIndex !== -1) {
      storageItems.value[itemIndex] = {
        ...item,
        ...updatedItem,
        grants_count: item.grants_count,
      };
    }
    editingStorageItem.value = null;
    invalidateStorageLogs();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось обновить предмет.';
  } finally {
    storageItemSaving.value = false;
  }
}


async function grantItem(payload: {
  itemId: number;
  characterId: number;
  quantity: number;
  reason: string | null;
}) {
  if (!canManageStorage.value || !myCharacterId.value) return;

  grantStorageItemSaving.value = true;
  error.value = null;
  try {
    await constantPartiesApi.createGrant(partyId.value, {
      item_id: payload.itemId,
      received_by_character_id: payload.characterId,
      granted_by_character_id: myCharacterId.value,
      quantity: payload.quantity,
      reason: payload.reason,
    });
    grantStorageItemDialogOpen.value = false;
    invalidateStorageLogs();
    await loadStorage();
    if (selectedHistoryCharacterId.value === payload.characterId) {
      await showCharacterHistory(
        payload.characterId,
        selectedHistoryCharacterName.value,
      );
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось выдать предмет.';
  } finally {
    grantStorageItemSaving.value = false;
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

onMounted(() => {
  if (constantPartyChatEnabled) {
    document.addEventListener('visibilitychange', onChatVisibilityChanged);
  }
  void load();
});

onUnmounted(() => {
  if (constantPartyChatEnabled) {
    document.removeEventListener('visibilitychange', onChatVisibilityChanged);
  }
});

watch(activeTab, (tab) => {
  if (tab === 'logs' && storageLogs.value.length === 0) {
    void loadStorageLogs(1);
  }
  if (tab === 'chat') {
    void acknowledgeIncomingMessages(true);
  }
});

watch(
  [storageLogsDateFrom, storageLogsDateTo, storageLogsSort],
  () => {
    if (activeTab.value === 'logs') {
      void loadStorageLogs(1);
    }
  },
);

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
    <div class="fixed right-8 top-[100px] z-30 md:hidden">
      <BackIconButton
        aria-label="Назад"
        title="Назад"
        @click="router.back()"
      />
    </div>

    <div class="relative mx-auto flex max-w-7xl md:gap-3">
      <div class="sticky top-[100px] z-30 hidden h-9 shrink-0 self-start md:block">
        <BackIconButton
          aria-label="Назад"
          title="Назад"
          @click="router.back()"
        />
      </div>

      <div class="min-w-0 flex-1">
      <div v-if="loading" class="flex justify-center py-10">
        <Spinner class="h-8 w-8" />
      </div>

      <template v-else-if="party">
        <div class="mb-5 flex items-center justify-between gap-3">
          <h1 class="min-w-0 truncate text-2xl font-bold tracking-tight">
            {{ party.name }}
          </h1>
          <Button
            v-if="isCurrentUserLeader"
            type="button"
            variant="outline"
            size="sm"
            class="shrink-0 gap-2 border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
            :disabled="dissolvePartyLoading"
            title="Распустить КП"
            @click="dissolvePartyDialogOpen = true"
          >
            <Trash2 class="size-4" />
            <span class="hidden sm:inline">Распустить КП</span>
          </Button>
          <Button
            v-else-if="myMember && myMember.role !== 'leader'"
            type="button"
            variant="outline"
            size="sm"
            class="shrink-0 gap-2 text-destructive hover:text-destructive"
            :disabled="memberActionLoading"
            title="Покинуть КП"
            @click="leavePartyDialogOpen = true"
          >
            <LogOut class="size-4" />
            <span class="hidden sm:inline">Покинуть КП</span>
          </Button>
        </div>

        <div v-if="error" class="mb-4 rounded-md border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          {{ error }}
        </div>

        <div class="mb-4 flex gap-1 overflow-x-auto whitespace-nowrap border-b">
          <button type="button" class="px-3 py-2 text-sm font-medium" :class="activeTab === 'members' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground'" @click="activeTab = 'members'">
            Состав
          </button>
          <button v-if="constantPartyChatEnabled" type="button" class="px-3 py-2 text-sm font-medium" :class="activeTab === 'chat' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground'" @click="activeTab = 'chat'">
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
          <button type="button" class="px-3 py-2 text-sm font-medium" :class="activeTab === 'logs' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground'" @click="activeTab = 'logs'">
            Логи
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
                    <span class="block truncate text-sm font-medium">
                      {{ character.name }}
                    </span>
                    <span
                      v-if="character.isActive"
                      class="mt-1 flex min-w-0 items-center gap-1.5"
                    >
                      <span class="truncate text-xs text-muted-foreground">
                        {{ character.role === 'leader' ? 'Лидер' : 'Участник' }}
                      </span>
                      <Badge
                        v-if="character.canManageStorage"
                        variant="outline"
                        class="h-5 shrink-0 gap-1 border-emerald-600/25 px-1.5 py-0 text-[10px] font-medium text-emerald-700 dark:text-emerald-400"
                      >
                        <KeyRound class="size-3" />
                        Хранилище
                      </Badge>
                    </span>
                    <span
                      v-else
                      class="mt-0.5 block truncate text-xs text-muted-foreground"
                    >
                      Неактивен
                    </span>
                  </span>
                  <span class="text-xl leading-none text-muted-foreground">›</span>
                </button>
              </div>


            </div>

            <div class="min-w-0 space-y-4">
              <div
                v-if="selectedRosterCharacter"
                class="flex min-h-24 items-center gap-4 rounded-lg border bg-background px-5 py-4"
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
                  <p class="truncate text-lg font-semibold">
                    {{ selectedRosterCharacter.name }}
                  </p>
                  <div
                    v-if="selectedRosterCharacter.isActive"
                    class="mt-1.5 flex flex-wrap items-center gap-1.5"
                  >
                    <Badge
                      :variant="selectedRosterCharacter.role === 'leader' ? 'default' : 'secondary'"
                      class="gap-1"
                    >
                      <Crown
                        v-if="selectedRosterCharacter.role === 'leader'"
                        class="size-3"
                      />
                      {{ selectedRosterCharacter.role === 'leader' ? 'Лидер' : 'Участник' }}
                    </Badge>
                    <Badge
                      v-if="selectedRosterCharacter.canManageStorage"
                      variant="outline"
                      class="gap-1 border-emerald-600/25 text-emerald-700 dark:text-emerald-400"
                    >
                      <KeyRound class="size-3" />
                      Управление хранилищем
                    </Badge>
                  </div>
                  <p
                    v-else
                    class="mt-1 text-sm text-muted-foreground"
                  >
                    Неактивен · вышел {{ formatHistoryDate(selectedRosterCharacter.leftAt) }}
                  </p>
                </div>

                <DropdownMenu
                  v-if="
                    isCurrentUserLeader
                    && selectedRosterCharacter.isActive
                    && selectedRosterCharacter.role !== 'leader'
                    && selectedRosterCharacter.memberId !== null
                  "
                >
                  <DropdownMenuTrigger as-child>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="shrink-0"
                      :disabled="memberActionLoading"
                      title="Управление участником"
                      aria-label="Управление участником"
                    >
                      <MoreHorizontal class="size-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent
                    align="end"
                    class="w-64"
                  >
                    <DropdownMenuItem
                      class="gap-2"
                      @select="openTransferLeadershipDialog(selectedRosterCharacter)"
                    >
                      <Crown class="size-4 text-amber-600" />
                      Передать лидерство
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      class="gap-2"
                      @select="
                        toggleStorageRight(
                          selectedRosterCharacter.memberId!,
                          !selectedRosterCharacter.canManageStorage,
                        )
                      "
                    >
                      <KeyRound class="size-4 text-emerald-600" />
                      {{
                        selectedRosterCharacter.canManageStorage
                          ? 'Запретить управление хранилищем'
                          : 'Разрешить управление хранилищем'
                      }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      class="gap-2 text-destructive focus:text-destructive"
                      @select="openRemoveMemberDialog(selectedRosterCharacter)"
                    >
                      <UserMinus class="size-4" />
                      Исключить из КП
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

              </div>

              <div class="rounded-lg border bg-background">
                <div class="px-5 pb-3 pt-5">
                  <h2 class="text-base font-semibold">История полученных предметов</h2>
                  <p class="mt-2 text-sm text-muted-foreground">
                    Всего предметов: {{ totalCharacterGrantQuantity }}
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
                          <TableCell class="break-words px-1 py-4 align-top">{{ grant.quantity }}</TableCell>
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
          <ConfirmDialog
            :open="transferLeadershipTarget !== null"
            title="Передать лидерство?"
            :description="
              transferLeadershipTarget
                ? `Персонаж «${transferLeadershipTarget.name}» станет лидером КП. Вы станете обычным участником без доступа к управлению хранилищем.`
                : ''
            "
            confirm-label="Передать"
            confirm-variant="default"
            :loading="memberActionLoading"
            @update:open="
              (open) => {
                if (!open) transferLeadershipTarget = null;
              }
            "
            @confirm="confirmTransferLeadership"
          />

          <ConfirmDialog
            :open="removeMemberTarget !== null"
            title="Исключить персонажа из КП?"
            :description="
              removeMemberTarget
                ? `Персонаж «${removeMemberTarget.name}» будет исключен из КП. История полученных им предметов сохранится.`
                : ''
            "
            confirm-label="Исключить"
            confirm-variant="destructive"
            :loading="memberActionLoading"
            @update:open="
              (open) => {
                if (!open) removeMemberTarget = null;
              }
            "
            @confirm="confirmRemoveMember"
          />

          <ConfirmDialog
            v-model:open="leavePartyDialogOpen"
            title="Покинуть КП?"
            description="Вы покинете состав КП. История полученных предметов сохранится."
            confirm-label="Покинуть"
            confirm-variant="destructive"
            :loading="memberActionLoading"
            @confirm="confirmLeaveParty"
          />

          <ConfirmDialog
            v-model:open="dissolvePartyDialogOpen"
            title="Распустить КП?"
            confirm-label="Распустить навсегда"
            confirm-variant="destructive"
            :loading="dissolvePartyLoading"
            @confirm="confirmDissolveParty"
          >
            <template #description>
              <div class="space-y-3">
                <p>
                  КП «{{ party.name }}» будет распущена, а все участники получат уведомление.
                </p>
                <p>
                  Будут навсегда удалены состав и бывшие участники, приглашения, чат, все предметы и остатки хранилища, история выдач и журнал действий.
                </p>
                <p class="font-medium text-destructive">
                  Это действие нельзя отменить, а данные невозможно восстановить.
                </p>
              </div>
            </template>
          </ConfirmDialog>
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
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">
                    {{ invitation.invited_character?.name ?? 'Персонаж' }}
                  </p>
                  <p
                    v-if="invitation.created_at"
                    class="mt-1 text-xs text-muted-foreground"
                  >
                    {{ formatDate(invitation.created_at) }}
                  </p>
                  <div
                    v-if="invitation.message"
                    class="mt-2 border-l-2 border-primary/30 pl-3"
                  >
                    <p class="text-xs font-medium text-muted-foreground">
                      Сообщение
                    </p>
                    <p class="mt-1 whitespace-pre-wrap break-words text-sm">
                      {{ invitation.message }}
                    </p>
                  </div>
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

        <StorageLogsTab
          v-else-if="activeTab === 'logs'"
          v-model:date-range="storageLogsDateRange"
          v-model:sort="storageLogsSort"
          :logs="storageLogs"
          :loading="storageLogsLoading"
          :current-page="storageLogsPage"
          :last-page="storageLogsLastPage"
          @page-change="loadStorageLogs"
        />

        <ConstantPartyChatTab
          v-else-if="constantPartyChatEnabled && activeTab === 'chat'"
          v-model:draft="chatBody"
          :messages="messages"
          :current-character-id="myCharacterId"
          :online-characters="chatOnlineCharacters"
          :socket-connected="chatSocketConnected"
          :socket-authenticated="chatSocketAuthenticated"
          :loading="chatLoading"
          :sending="chatSending"
          @send="sendMessage"
        />
        <section v-else class="space-y-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="font-semibold">Предметы</h2>
              <p class="mt-0.5 text-sm text-muted-foreground">
                Предметов в хранилище: {{ storageItems.length }}
              </p>
            </div>
            <div
              v-if="canManageStorage"
              class="flex flex-wrap gap-2"
            >
              <Button
                type="button"
                variant="outline"
                class="border-emerald-700/30 text-emerald-800 hover:bg-emerald-500/10 hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-200"
                @click="addStorageItemDialogOpen = true"
              >
                <PackagePlus class="size-4" />
                Добавить предмет
              </Button>
              <Button
                type="button"
                :disabled="storageItems.length === 0 || members.length === 0"
                @click="grantStorageItemDialogOpen = true"
              >
                <Gift class="size-4" />
                Выдать предмет
              </Button>
            </div>
          </div>

          <div class="overflow-hidden rounded-lg border bg-background">
            <div
              v-if="storageItems.length === 0"
              class="p-6 text-center text-sm text-muted-foreground"
            >
              В хранилище пока нет предметов.
            </div>
            <div
              v-for="item in storageItems"
              :key="item.id"
              class="flex items-center justify-between gap-3 border-b p-4 last:border-b-0"
            >
              <div class="min-w-0">
                <p class="truncate font-medium">{{ item.name }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                  Остаток: {{ item.quantity === null ? 'без ограничения' : item.quantity }}
                  · выдач: {{ item.grants_count ?? 0 }}
                </p>
              </div>
              <Button
                v-if="canManageStorage"
                type="button"
                variant="ghost"
                size="icon"
                class="shrink-0"
                title="Редактировать предмет"
                aria-label="Редактировать предмет"
                @click="openStorageItemEditor(item)"
              >
                <Pencil class="size-4" />
              </Button>
            </div>
          </div>

          <AddStorageItemDialog
            v-model:open="addStorageItemDialogOpen"
            :saving="addStorageItemSaving"
            @save="addStorageItem"
          />
          <GrantStorageItemDialog
            v-model:open="grantStorageItemDialogOpen"
            :saving="grantStorageItemSaving"
            :items="storageItems"
            :members="members"
            @save="grantItem"
          />
          <EditStorageItemDialog
            :open="editingStorageItem !== null"
            :item="editingStorageItem"
            :saving="storageItemSaving"
            @update:open="
              (open) => {
                if (!open) editingStorageItem = null;
              }
            "
            @save="saveStorageItem"
          />
        </section>
      </template>

      <div v-else class="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        {{ error ?? 'Конст пати не найдена.' }}
      </div>
    </div>
    </div>
  </div>
</template>
