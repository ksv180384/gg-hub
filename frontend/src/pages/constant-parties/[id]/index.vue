<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { Spinner } from '@/shared/ui';
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
const activeTab = ref<'members' | 'chat' | 'storage'>('members');
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

const myMember = computed(() => party.value?.my_member ?? null);
const canManageStorage = computed(() => storageContext.value.can_manage_storage || myMember.value?.role === 'leader');
const myCharacterId = computed(() => storageContext.value.my_character_id || myMember.value?.character_id || null);
const members = computed(() => party.value?.members ?? []);

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
    inviteQuery.value = '';
    inviteCandidates.value = [];
    inviteMessage.value = '';
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

function formatDate(value?: string | null) {
  if (!value) return '';
  return new Date(value).toLocaleString('ru-RU');
}

onMounted(load);

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
  <div class="container">
    <div class="mx-auto max-w-5xl">
      <div v-if="loading" class="flex justify-center py-10">
        <Spinner class="h-8 w-8" />
      </div>

      <template v-else-if="party">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ party.name }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">
              {{ party.server?.name ?? 'Сервер' }} · лидер {{ party.leader?.name ?? '...' }}
            </p>
          </div>
          <div class="rounded-md border px-3 py-1.5 text-xs text-muted-foreground">
            {{ myMember?.role === 'leader' ? 'Лидер' : canManageStorage ? 'Управление хранилищем' : 'Участник' }}
          </div>
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
        </div>

        <section v-if="activeTab === 'members'" class="grid gap-4 lg:grid-cols-[1fr_20rem]">
          <div class="space-y-4">
            <div class="overflow-hidden rounded-lg border bg-background">
              <div
                v-for="member in members"
                :key="member.id"
                class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-b-0"
                :class="selectedHistoryCharacterId === member.character_id ? 'bg-muted/50' : ''"
              >
                <button
                  type="button"
                  class="min-w-0 flex-1 text-left"
                  @click="showCharacterHistory(member.character_id, member.character?.name ?? 'Персонаж')"
                >
                  <span class="block font-medium">{{ member.character?.name ?? 'Персонаж' }}</span>
                  <span class="mt-1 block text-xs text-muted-foreground">
                    {{ member.role === 'leader' ? 'Лидер' : 'Участник' }}
                    <span v-if="member.can_manage_storage"> · хранилище</span>
                  </span>
                </button>
                <div v-if="myMember?.role === 'leader' && member.role !== 'leader'" class="flex items-center gap-2">
                  <label class="flex items-center gap-2 text-xs text-muted-foreground">
                    <input type="checkbox" :checked="member.can_manage_storage" @change="toggleStorageRight(member.id, ($event.target as HTMLInputElement).checked)" />
                    Хранилище
                  </label>
                  <button type="button" class="h-8 rounded-md border px-3 text-xs font-medium text-destructive" @click="removeMember(member.id)">
                    Исключить
                  </button>
                </div>
              </div>

              <div v-if="formerMembers.length > 0" class="border-t bg-muted/20">
                <p class="px-4 pb-1 pt-3 text-xs font-medium text-muted-foreground">
                  Бывшие участники
                </p>
                <button
                  v-for="formerMember in formerMembers"
                  :key="formerMember.id"
                  type="button"
                  class="flex w-full items-center justify-between gap-3 border-t px-4 py-3 text-left opacity-55 transition-opacity hover:opacity-80"
                  :class="selectedHistoryCharacterId === formerMember.character_id ? 'bg-muted opacity-80' : ''"
                  @click="showCharacterHistory(formerMember.character_id, formerMember.character?.name ?? 'Персонаж')"
                >
                  <span class="font-medium">{{ formerMember.character?.name ?? 'Персонаж' }}</span>
                  <span class="shrink-0 text-xs text-muted-foreground">
                    Вышел {{ formatDate(formerMember.left_at) }}
                  </span>
                </button>
              </div>
            </div>

            <div v-if="selectedHistoryCharacterId !== null" class="overflow-hidden rounded-lg border bg-background">
              <div class="border-b px-4 py-3">
                <h2 class="text-sm font-semibold">История полученных предметов</h2>
                <p class="mt-1 text-xs text-muted-foreground">{{ selectedHistoryCharacterName }}</p>
              </div>
              <div v-if="loadingCharacterGrants" class="flex justify-center py-8">
                <Spinner class="h-6 w-6" />
              </div>
              <div v-else-if="characterGrants.length === 0" class="p-6 text-center text-sm text-muted-foreground">
                Полученных предметов пока нет.
              </div>
              <div
                v-for="grant in characterGrants"
                v-else
                :key="grant.id"
                class="border-b px-4 py-3 last:border-b-0"
              >
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <p class="font-medium">{{ grant.item?.name ?? 'Предмет' }}</p>
                  <span class="text-xs text-muted-foreground">{{ formatDate(grant.granted_at) }}</span>
                </div>
                <p v-if="grant.reason" class="mt-1 text-sm text-muted-foreground">
                  {{ grant.reason }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                  Выдал: {{ grant.granted_by_character?.name ?? 'Персонаж' }}
                </p>
              </div>
            </div>
          </div>

          <aside class="space-y-4">
            <form v-if="canManageStorage" class="rounded-lg border bg-background p-4" @submit.prevent>
              <h2 class="mb-3 text-sm font-semibold">Пригласить персонажа</h2>
              <input
                v-model="inviteQuery"
                class="mb-2 h-9 w-full rounded-md border bg-background px-3 text-sm"
                placeholder="Ник персонажа"
              />
              <textarea v-model="inviteMessage" class="mb-3 min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm" placeholder="Сообщение" />
              <div v-if="searchingInviteCandidates" class="py-3 text-sm text-muted-foreground">
                Поиск...
              </div>
              <div v-else-if="inviteQuery.trim().length >= 2 && inviteCandidates.length === 0" class="py-3 text-sm text-muted-foreground">
                Персонажи не найдены.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="candidate in inviteCandidates"
                  :key="candidate.id"
                  class="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
                >
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ candidate.name }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ candidate.server?.name ?? party.server?.name }}</p>
                  </div>
                  <button
                    type="button"
                    class="h-8 shrink-0 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground disabled:opacity-60"
                    :disabled="invitingCharacterId === candidate.id"
                    @click="invite(candidate)"
                  >
                    Пригласить
                  </button>
                </div>
              </div>
            </form>

            <div class="rounded-lg border bg-background p-4">
              <h2 class="mb-3 text-sm font-semibold">Приглашения</h2>
              <p v-if="invitations.length === 0" class="text-sm text-muted-foreground">Активных приглашений нет.</p>
              <div v-for="invitation in invitations" :key="invitation.id" class="border-b py-2 text-sm last:border-b-0">
                <p class="font-medium">{{ invitation.invited_character?.name ?? 'Персонаж' }}</p>
                <p class="text-xs text-muted-foreground">{{ invitation.status }}</p>
              </div>
            </div>
          </aside>
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
