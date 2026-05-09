<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import {
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  ColorPicker,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  Input,
  Label,
} from '@/shared/ui';
import { guildBankApi, type GuildBankItem, type GuildBankGrant } from '@/shared/api/guildBankApi';
import { guildsApi, type GuildRosterMember } from '@/shared/api/guildsApi';
import type { ApiError } from '@/shared/api/errors';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { cn } from '@/shared/lib/utils';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

const loading = ref(false);
const error = ref('');
const items = ref<GuildBankItem[]>([]);
const selectedItemId = ref<number | null>(null);
const itemsSearch = ref('');

const grantsLoading = ref(false);
const grantsError = ref('');
const grants = ref<GuildBankGrant[]>([]);
const grantsSearch = ref('');

const myPermissionSlugs = ref<string[]>([]);
const canAddItems = computed(() => myPermissionSlugs.value.includes('dobavliat-predmety'));
const canDeleteItems = computed(() => myPermissionSlugs.value.includes('udaliat-predmety'));
const canGrantItems = computed(() => myPermissionSlugs.value.includes('peredavat-predmety-polzovateliam'));
const dkpEnabled = ref(false);

const itemFormOpen = ref(false);
const itemFormSaving = ref(false);
const itemFormError = ref('');
const itemEditing = ref<GuildBankItem | null>(null);
const itemForm = ref({
  name: '',
  description: '',
  tier: '' as string,
  color: '',
  dkp_cost: '' as string,
  quantity: '' as string,
});

const grantFormOpen = ref(false);
const grantFormSaving = ref(false);
const grantFormError = ref('');
const revokingGrantId = ref<number | null>(null);
const revokeDialogOpen = ref(false);
const grantPendingRevoke = ref<GuildBankGrant | null>(null);
const revokeDialogError = ref('');
const deleteItemDialogOpen = ref(false);
const itemPendingDelete = ref<GuildBankItem | null>(null);
const deleteItemDialogError = ref('');
const deletingItemId = ref<number | null>(null);

const restockModalOpen = ref(false);
const restockItem = ref<GuildBankItem | null>(null);
const restockAmount = ref('');
const restockSaving = ref(false);
const restockError = ref('');

const MAX_BANK_QUANTITY = 1_000_000_000;
const grantForm = ref({
  received_by_character_id: '' as string,
  reason: '',
});

// combobox (состав гильдии) для выдачи предмета
const rosterMembers = ref<GuildRosterMember[]>([]);
const rosterLoading = ref(false);
const rosterError = ref('');
const memberSelectOpen = ref(false);
const memberSearch = ref('');

const selectedRosterMember = computed(() => {
  const id = Number(grantForm.value.received_by_character_id);
  if (!id) return null;
  return rosterMembers.value.find((m) => m.character_id === id) ?? null;
});

const filteredRosterMembers = computed(() => {
  const q = memberSearch.value.trim().toLowerCase();
  if (!q) return rosterMembers.value;
  return rosterMembers.value.filter((m) => m.name.toLowerCase().includes(q));
});

async function loadRosterMembers() {
  if (!guildId.value || rosterLoading.value) return;
  rosterLoading.value = true;
  rosterError.value = '';
  try {
    const res = await guildsApi.getGuildRoster(guildId.value);
    rosterMembers.value = [...(res.members ?? [])].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
  } catch (e: unknown) {
    rosterMembers.value = [];
    rosterError.value = e instanceof Error ? e.message : 'Не удалось загрузить состав гильдии.';
  } finally {
    rosterLoading.value = false;
  }
}

function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

async function loadPermissions() {
  if (!guildId.value) return;
  try {
    const guild = await guildsApi.getGuildForSettings(guildId.value);
    myPermissionSlugs.value = guild.my_permission_slugs ?? [];
    dkpEnabled.value = guild.dkp_enabled ?? false;
  } catch {
    myPermissionSlugs.value = [];
    dkpEnabled.value = false;
  }
}

async function loadItems() {
  if (!guildId.value) return;
  loading.value = true;
  error.value = '';
  try {
    items.value = await guildBankApi.listItems(guildId.value);
    itemsSearch.value = '';
    if (selectedItemId.value == null && items.value.length) {
      selectedItemId.value = items.value[0].id;
    }
  } catch (e: unknown) {
    const st = (e as ApiError)?.status;
    error.value =
      st === 403 || st === 404 ? 'Нет доступа к банку гильдии.' : e instanceof Error ? e.message : 'Ошибка загрузки.';
    items.value = [];
    selectedItemId.value = null;
  } finally {
    loading.value = false;
  }
}

const selectedItem = computed(() => items.value.find((i) => i.id === selectedItemId.value) ?? null);

const filteredItems = computed(() => {
  const q = itemsSearch.value.trim().toLowerCase();
  if (!q) return items.value;
  return items.value.filter((i) => i.name.toLowerCase().includes(q));
});

const filteredGrants = computed(() => {
  const q = grantsSearch.value.trim().toLowerCase();
  if (!q) return grants.value;
  return grants.value.filter((g) => {
    const name = (g.received_by_character?.name ?? '').toLowerCase();
    const id = String(g.received_by_character_id);
    return name.includes(q) || id.includes(q);
  });
});

async function loadGrants(itemId: number) {
  if (!guildId.value) return;
  grantsLoading.value = true;
  grantsError.value = '';
  try {
    grants.value = await guildBankApi.listItemGrants(guildId.value, itemId);
    grantsSearch.value = '';
  } catch (e: unknown) {
    grantsError.value = e instanceof Error ? e.message : 'Не удалось загрузить историю выдач.';
    grants.value = [];
  } finally {
    grantsLoading.value = false;
  }
}

async function selectItem(itemId: number) {
  selectedItemId.value = itemId;
  await loadGrants(itemId);
}

function openCreateItem() {
  itemEditing.value = null;
  itemForm.value = { name: '', description: '', tier: '', color: '', dkp_cost: '', quantity: '' };
  itemFormError.value = '';
  itemFormOpen.value = true;
}

function openEditItem(item: GuildBankItem) {
  itemEditing.value = item;
  itemForm.value = {
    name: item.name,
    description: item.description ?? '',
    tier: item.tier ?? '',
    color: item.color ?? '',
    dkp_cost: item.dkp_cost == null ? '' : String(item.dkp_cost),
    quantity: item.quantity == null ? '' : String(item.quantity),
  };
  itemFormError.value = '';
  itemFormOpen.value = true;
}

async function saveItem() {
  if (!guildId.value) return;
  itemFormError.value = '';
  const name = itemForm.value.name.trim();
  if (!name) {
    itemFormError.value = 'Введите название предмета.';
    return;
  }
  itemFormSaving.value = true;
  try {
    const payload = {
      name,
      description: itemForm.value.description.trim() || null,
      tier: itemForm.value.tier.trim() || null,
      color: itemForm.value.color.trim() || null,
      dkp_cost: dkpEnabled.value && itemForm.value.dkp_cost.trim()
        ? Number(itemForm.value.dkp_cost.trim())
        : null,
      quantity: itemForm.value.quantity.trim() !== ''
        ? Number(itemForm.value.quantity.trim())
        : null,
    };
    if (itemEditing.value) {
      const updated = await guildBankApi.updateItem(guildId.value, itemEditing.value.id, payload);
      items.value = items.value.map((i) => (i.id === updated.id ? updated : i));
    } else {
      const created = await guildBankApi.createItem(guildId.value, payload);
      items.value = [...items.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
      selectedItemId.value = created.id;
      await loadGrants(created.id);
    }
    itemFormOpen.value = false;
  } catch (e: unknown) {
    itemFormError.value = e instanceof Error ? e.message : 'Не удалось сохранить предмет.';
  } finally {
    itemFormSaving.value = false;
  }
}

function itemHasActiveGrants(item: GuildBankItem): boolean {
  return (item.grants_count ?? 0) > 0;
}

function openRestockModal(item: GuildBankItem) {
  restockItem.value = item;
  restockAmount.value = '1';
  restockError.value = '';
  restockModalOpen.value = true;
}

function closeRestockModal() {
  if (restockSaving.value) return;
  restockModalOpen.value = false;
  restockItem.value = null;
  restockAmount.value = '';
  restockError.value = '';
}

async function saveRestock() {
  if (!guildId.value || !restockItem.value) return;
  restockError.value = '';
  const add = Number(restockAmount.value.trim());
  if (!Number.isFinite(add) || add < 1 || !Number.isInteger(add)) {
    restockError.value = 'Укажите целое число не меньше 1.';
    return;
  }
  const item = restockItem.value;
  const current = item.quantity;
  let newQ: number;
  if (current == null) {
    newQ = add;
  } else {
    newQ = current + add;
    if (newQ > MAX_BANK_QUANTITY) {
      restockError.value = `Итоговый остаток не может превышать ${MAX_BANK_QUANTITY.toLocaleString('ru-RU')}.`;
      return;
    }
  }
  restockSaving.value = true;
  try {
    const updated = await guildBankApi.updateItem(guildId.value, item.id, { quantity: newQ });
    items.value = items.value.map((i) => (i.id === updated.id ? updated : i));
    restockModalOpen.value = false;
    restockItem.value = null;
    restockAmount.value = '';
  } catch (e: unknown) {
    restockError.value = e instanceof Error ? e.message : 'Не удалось обновить количество.';
  } finally {
    restockSaving.value = false;
  }
}

function openDeleteItemDialog(item: GuildBankItem) {
  if (itemHasActiveGrants(item)) return;
  itemPendingDelete.value = item;
  deleteItemDialogError.value = '';
  deleteItemDialogOpen.value = true;
}

function closeDeleteItemDialog() {
  if (deletingItemId.value != null) return;
  deleteItemDialogOpen.value = false;
  itemPendingDelete.value = null;
  deleteItemDialogError.value = '';
}

async function confirmDeleteItem() {
  const item = itemPendingDelete.value;
  if (!item || !guildId.value) return;
  deletingItemId.value = item.id;
  deleteItemDialogError.value = '';
  try {
    await guildBankApi.deleteItem(guildId.value, item.id);
    items.value = items.value.filter((i) => i.id !== item.id);
    if (selectedItemId.value === item.id) {
      selectedItemId.value = items.value[0]?.id ?? null;
      if (selectedItemId.value) await loadGrants(selectedItemId.value);
      else grants.value = [];
    }
    deleteItemDialogOpen.value = false;
    itemPendingDelete.value = null;
  } catch (e: unknown) {
    deleteItemDialogError.value =
      e instanceof Error ? e.message : 'Не удалось удалить предмет.';
  } finally {
    deletingItemId.value = null;
  }
}

function openGrant() {
  if (!selectedItem.value) return;
  grantForm.value = { received_by_character_id: '', reason: '' };
  grantFormError.value = '';
  grantFormOpen.value = true;
  memberSearch.value = '';
  memberSelectOpen.value = false;
  // лениво подгружаем состав для селекта
  if (rosterMembers.value.length === 0) {
    void loadRosterMembers();
  }
}

async function saveGrant() {
  if (!guildId.value || !selectedItem.value) return;
  grantFormError.value = '';
  const receivedId = Number(grantForm.value.received_by_character_id);
  if (!receivedId) {
    grantFormError.value = 'Укажите персонажа, который получил предмет.';
    return;
  }
  const reasonTrimmed = grantForm.value.reason.trim();
  grantFormSaving.value = true;
  try {
    const created = await guildBankApi.createGrant(guildId.value, {
      guild_bank_item_id: selectedItem.value.id,
      received_by_character_id: receivedId,
      reason: reasonTrimmed === '' ? null : reasonTrimmed,
    });
    grants.value = [created, ...grants.value];
    // обновим счётчик/последнюю выдачу локально
    items.value = items.value.map((i) =>
      i.id === selectedItem.value!.id
        ? {
            ...i,
            grants_count: (i.grants_count ?? 0) + 1,
            last_granted_at: created.granted_at,
            quantity: i.quantity == null ? null : Math.max(0, i.quantity - 1),
          }
        : i
    );
    grantFormOpen.value = false;
  } catch (e: unknown) {
    grantFormError.value = e instanceof Error ? e.message : 'Не удалось выдать предмет.';
  } finally {
    grantFormSaving.value = false;
  }
}

function openRevokeGrantDialog(grant: GuildBankGrant) {
  grantPendingRevoke.value = grant;
  revokeDialogError.value = '';
  revokeDialogOpen.value = true;
}

function closeRevokeDialog() {
  if (revokingGrantId.value != null) return;
  revokeDialogOpen.value = false;
  grantPendingRevoke.value = null;
  revokeDialogError.value = '';
}

async function confirmRevokeGrant() {
  const grant = grantPendingRevoke.value;
  if (!grant || !guildId.value || !selectedItem.value) return;
  revokingGrantId.value = grant.id;
  revokeDialogError.value = '';
  try {
    const { id: revokedId } = await guildBankApi.revokeGrant(guildId.value, grant.id);
    grants.value = grants.value.filter((g) => g.id !== revokedId);
    items.value = items.value.map((i) =>
      i.id === selectedItem.value!.id
        ? {
            ...i,
            grants_count: Math.max(0, (i.grants_count ?? 0) - 1),
            quantity: i.quantity == null ? null : i.quantity + 1,
          }
        : i
    );
    revokeDialogOpen.value = false;
    grantPendingRevoke.value = null;
  } catch (e: unknown) {
    revokeDialogError.value =
      e instanceof Error ? e.message : 'Не удалось отменить выдачу.';
  } finally {
    revokingGrantId.value = null;
  }
}

onMounted(async () => {
  await Promise.all([loadPermissions(), loadItems()]);
  if (selectedItemId.value) {
    await loadGrants(selectedItemId.value);
  }
});
</script>

<template>
  <div class="container py-6 md:py-8 overflow-x-hidden">
    <div class="min-w-0 w-full space-y-4">
      <div class="max-w-2xl">
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <h1 class="text-xl font-semibold">Хранилище гильдии</h1>
        </div>

        <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
        <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

        <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <Card>
        <CardHeader class="flex flex-row items-center justify-between gap-2 space-y-0 p-2">
          <CardTitle class="text-base">Предметы</CardTitle>
          <Button v-if="canAddItems" size="sm" @click="openCreateItem">Добавить</Button>
        </CardHeader>
        <CardContent class="space-y-2 px-2 pb-2 pt-0">
          <p v-if="!items.length" class="text-sm text-muted-foreground">Пока нет предметов.</p>
          <div v-else class="space-y-2">
            <Input
              v-model="itemsSearch"
              type="text"
              placeholder="Поиск по названию..."
              class="h-9"
            />

            <p v-if="filteredItems.length === 0" class="text-sm text-muted-foreground">
              Ничего не найдено.
            </p>

            <ul v-else class="space-y-1">
              <li
                v-for="i in filteredItems"
                :key="i.id"
                :class="[
                  'flex items-center justify-between gap-2 rounded border px-3 py-2 cursor-pointer hover:bg-accent',
                  i.id === selectedItemId ? 'bg-accent' : '',
                ]"
                @click="selectItem(i.id)"
              >
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <span
                      v-if="i.color"
                      class="inline-block h-2.5 w-2.5 rounded-full border"
                      :style="{ backgroundColor: i.color }"
                      aria-hidden="true"
                    />
                    <div class="min-w-0">
                      <div class="truncate font-medium">{{ i.name }}</div>
                      <div class="text-xs text-muted-foreground">
                        <span v-if="i.tier">Тир: {{ i.tier }}</span>
                        <span v-if="i.quantity != null"> · Осталось: {{ i.quantity }}</span>
                        <span v-if="dkpEnabled && i.dkp_cost != null"> · ДКП: {{ i.dkp_cost }}</span>
                        <span v-if="i.grants_count != null"> · Выдано: {{ i.grants_count }}</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div
                  v-if="canAddItems || canDeleteItems"
                  class="flex shrink-0 items-center gap-0.5"
                  @click.stop
                >
                  <Button
                    v-if="canAddItems"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 shrink-0 cursor-pointer inline-flex items-center justify-center"
                    title="Добавить предметы"
                    aria-label="Добавить предметы"
                    @click="openRestockModal(i)"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="18"
                      height="18"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      class="shrink-0"
                      aria-hidden="true"
                    >
                      <line x1="15" x2="15" y1="12" y2="18" />
                      <line x1="12" x2="18" y1="15" y2="15" />
                      <rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
                      <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
                    </svg>
                  </Button>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 cursor-pointer inline-flex items-center justify-center"
                        aria-label="Действия с предметом"
                        title="Действия"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="18"
                          height="18"
                          viewBox="0 0 24 24"
                          fill="currentColor"
                          aria-hidden="true"
                        >
                          <circle cx="12" cy="5" r="2" />
                          <circle cx="12" cy="12" r="2" />
                          <circle cx="12" cy="19" r="2" />
                        </svg>
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="min-w-44">
                      <DropdownMenuItem v-if="canAddItems" @click="openEditItem(i)">
                        Редактировать
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        v-if="canDeleteItems"
                        class="text-destructive focus:text-destructive"
                        :disabled="itemHasActiveGrants(i)"
                        :title="
                          itemHasActiveGrants(i)
                            ? 'Нельзя удалить: есть активные выдачи. Сначала отмените их в истории.'
                            : undefined
                        "
                        @click="openDeleteItemDialog(i)"
                      >
                        Удалить
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </li>
            </ul>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between gap-2 space-y-0 p-2">
          <CardTitle class="text-base">
            История выдач
            <span v-if="selectedItem" class="text-muted-foreground font-normal">— {{ selectedItem.name }}</span>
          </CardTitle>
          <Button v-if="selectedItem && canGrantItems" size="sm" @click="openGrant">Выдать</Button>
        </CardHeader>
        <CardContent class="space-y-2 px-2 pb-2 pt-0">
          <p v-if="!selectedItem" class="text-sm text-muted-foreground">Выберите предмет слева.</p>
          <template v-else>
            <p v-if="grantsLoading" class="text-sm text-muted-foreground">Загрузка истории…</p>
            <p v-else-if="grantsError" class="text-sm text-destructive">{{ grantsError }}</p>
            <p v-else-if="!grants.length" class="text-sm text-muted-foreground">
              Пока нет выдач.
            </p>
            <div v-else class="space-y-3">
              <div class="flex items-center gap-2">
                <Input
                  v-model="grantsSearch"
                  type="text"
                  placeholder="Поиск по нику получателя..."
                  class="h-9"
                />
              </div>

              <p v-if="filteredGrants.length === 0" class="text-sm text-muted-foreground">
                Ничего не найдено.
              </p>

              <ul v-else class="space-y-2">
                <li
                  v-for="g in filteredGrants"
                  :key="g.id"
                  class="rounded border p-3"
                >
                  <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                      <div class="flex flex-wrap items-center gap-2">
                        <div class="text-sm font-medium truncate">
                          {{ g.received_by_character?.name ?? `Персонаж #${g.received_by_character_id}` }}
                        </div>
                      </div>
                      <div class="text-xs text-muted-foreground">
                        {{ formatDateTime(g.granted_at) }}
                        <span v-if="g.granted_by_character?.name"> · выдал: {{ g.granted_by_character.name }}</span>
                      </div>
                    </div>
                    <Button
                      v-if="canGrantItems"
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="h-8 w-8 shrink-0 self-start text-muted-foreground hover:text-destructive"
                      title="Отменить выдачу"
                      aria-label="Отменить выдачу"
                      :disabled="revokingGrantId === g.id"
                      @click="openRevokeGrantDialog(g)"
                    >
                      <svg
                        v-if="revokingGrantId === g.id"
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="animate-spin"
                        aria-hidden="true"
                      >
                        <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                      </svg>
                      <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                        <path d="M3 3v5h5" />
                      </svg>
                    </Button>
                  </div>
                  <div class="mt-2 text-sm whitespace-pre-wrap">
                    {{ g.reason?.trim() ? g.reason : '—' }}
                  </div>
                </li>
              </ul>
            </div>
          </template>
        </CardContent>
      </Card>
        </div>
      </div>
    </div>

    <!-- Простые модалки через условный рендер (MVP). -->
    <div v-if="itemFormOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-lg rounded-xl border border-border bg-card text-card-foreground shadow-lg">
        <div class="border-b border-border px-4 py-3">
          <div class="text-base font-semibold">
            {{ itemEditing ? 'Редактирование предмета' : 'Новый предмет' }}
          </div>
        </div>
        <div class="space-y-4 px-4 py-4">
          <div class="space-y-2">
            <Label for="item-name">Название *</Label>
            <Input id="item-name" v-model="itemForm.name" type="text" maxlength="255" required />
          </div>
          <div class="space-y-2">
            <Label for="item-desc">Описание</Label>
            <textarea
              id="item-desc"
              v-model="itemForm.description"
              rows="3"
              class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
              placeholder="Описание (необязательно)"
            />
          </div>
          <div
            class="grid grid-cols-1 gap-3"
            :class="dkpEnabled ? 'sm:grid-cols-2' : 'sm:grid-cols-3'"
          >
            <div class="space-y-2">
              <Label for="item-tier">Тир</Label>
              <Input id="item-tier" v-model="itemForm.tier" type="text" maxlength="50" placeholder="Например S / A / 3" />
            </div>
            <div class="space-y-2">
              <Label for="item-color">Цвет</Label>
              <ColorPicker id="item-color" v-model="itemForm.color" />
            </div>
            <div class="space-y-2">
              <Label for="item-qty">Количество</Label>
              <Input id="item-qty" v-model="itemForm.quantity" type="number" min="0" placeholder="∞" />
            </div>
            <div v-if="dkpEnabled" class="space-y-2">
              <Label for="item-dkp">Стоимость ДКП</Label>
              <Input id="item-dkp" v-model="itemForm.dkp_cost" type="number" min="0" />
            </div>
          </div>

          <p v-if="itemFormError" class="text-sm text-destructive">{{ itemFormError }}</p>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
          <Button variant="outline" :disabled="itemFormSaving" @click="itemFormOpen = false">Отмена</Button>
          <Button :disabled="itemFormSaving" @click="saveItem">{{ itemFormSaving ? 'Сохранение…' : 'Сохранить' }}</Button>
        </div>
      </div>
    </div>

    <div v-if="restockModalOpen && restockItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-lg rounded-xl border border-border bg-card text-card-foreground shadow-lg">
        <div class="border-b border-border px-4 py-3">
          <div class="text-base font-semibold">Добавить предметы на склад</div>
          <div class="text-xs text-muted-foreground mt-0.5 truncate">{{ restockItem.name }}</div>
        </div>
        <div class="space-y-4 px-4 py-4">
          <p v-if="restockItem.quantity != null" class="text-sm text-muted-foreground">
            Сейчас на складе:
            <span class="font-medium text-foreground">{{ restockItem.quantity }}</span>.
            Укажите, сколько единиц добавить.
          </p>
          <p v-else class="text-sm text-muted-foreground">
            Остаток сейчас не ограничен (∞). Укажите, сколько единиц добавить — для предмета будет включён учёт количества на складе.
          </p>
          <div class="space-y-2">
            <Label for="restock-amount">Количество *</Label>
            <Input
              id="restock-amount"
              v-model="restockAmount"
              type="number"
              min="1"
              step="1"
              class="h-9"
              placeholder="Например 10"
            />
          </div>
          <p v-if="restockError" class="text-sm text-destructive">{{ restockError }}</p>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
          <Button variant="outline" :disabled="restockSaving" @click="closeRestockModal">Отмена</Button>
          <Button :disabled="restockSaving" @click="saveRestock">
            {{ restockSaving ? 'Сохранение…' : 'Добавить' }}
          </Button>
        </div>
      </div>
    </div>

    <div v-if="grantFormOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-lg rounded-xl border border-border bg-card text-card-foreground shadow-lg">
        <div class="border-b border-border px-4 py-3">
          <div class="text-base font-semibold">Выдать предмет</div>
          <div v-if="selectedItem" class="text-xs text-muted-foreground mt-0.5">{{ selectedItem.name }}</div>
        </div>
        <div class="space-y-4 px-4 py-4">
          <div class="space-y-2">
            <Label for="grant-received">Персонаж *</Label>

            <PopoverRoot v-model:open="memberSelectOpen">
              <PopoverTrigger
                id="grant-received"
                type="button"
                :class="cn(
                  'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:pointer-events-none disabled:opacity-50 [&>span]:line-clamp-1',
                  grantFormSaving ? 'opacity-60 pointer-events-none' : ''
                )"
              >
                <span
                  class="min-w-0 truncate text-left"
                  :class="!selectedRosterMember && 'text-muted-foreground'"
                >
                  {{ selectedRosterMember?.name ?? 'Выберите персонажа' }}
                </span>
                <span class="ml-2 shrink-0 opacity-50" aria-hidden="true">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6" />
                  </svg>
                </span>
              </PopoverTrigger>

              <ClientOnly>
              <PopoverPortal>
                <PopoverContent
                  side="bottom"
                  align="start"
                  :side-offset="4"
                  :class="cn(
                    'z-50 w-[var(--radix-popover-trigger-width)] max-h-[min(20rem,var(--radix-popover-content-available-height))] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                    'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    'data-[side=bottom]:slide-in-from-top-2'
                  )"
                >
                  <div class="p-1.5 border-b">
                    <Input
                      v-model="memberSearch"
                      type="text"
                      placeholder="Поиск по имени..."
                      class="h-8 text-sm"
                      @keydown.stop
                    />
                  </div>
                  <div class="max-h-[14rem] overflow-y-auto p-1">
                    <p v-if="rosterLoading" class="px-2 py-3 text-sm text-muted-foreground">
                      Загрузка состава…
                    </p>
                    <p v-else-if="rosterError" class="px-2 py-3 text-sm text-destructive">
                      {{ rosterError }}
                    </p>
                    <template v-else>
                      <button
                        v-for="m in filteredRosterMembers"
                        :key="m.character_id"
                        type="button"
                        :class="cn(
                          'relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground',
                          Number(grantForm.received_by_character_id) === m.character_id && 'bg-accent text-accent-foreground'
                        )"
                        @click="
                          grantForm.received_by_character_id = String(m.character_id);
                          memberSelectOpen = false;
                        "
                      >
                        {{ m.name }}
                      </button>
                      <p v-if="filteredRosterMembers.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
                        Ничего не найдено
                      </p>
                    </template>
                  </div>
                </PopoverContent>
              </PopoverPortal>
              </ClientOnly>
            </PopoverRoot>
          </div>
          <div class="space-y-2">
            <Label for="grant-reason">За что</Label>
            <textarea
              id="grant-reason"
              v-model="grantForm.reason"
              rows="3"
              class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
              placeholder="Необязательно"
            />
          </div>
          <p v-if="grantFormError" class="text-sm text-destructive">{{ grantFormError }}</p>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
          <Button variant="outline" :disabled="grantFormSaving" @click="grantFormOpen = false">Отмена</Button>
          <Button :disabled="grantFormSaving" @click="saveGrant">{{ grantFormSaving ? 'Выдача…' : 'Выдать' }}</Button>
        </div>
      </div>
    </div>

    <ConfirmDialog
      :open="deleteItemDialogOpen"
      title="Удалить предмет"
      confirm-label="Удалить"
      confirm-variant="destructive"
      :loading="deletingItemId != null"
      @confirm="confirmDeleteItem"
      @update:open="(v) => { if (!v) closeDeleteItemDialog(); }"
    >
      <template #description>
        <template v-if="itemPendingDelete">
          <p>
            Удалить предмет
            <span class="font-medium text-foreground">«{{ itemPendingDelete.name }}»</span>
            из хранилища? Это действие нельзя отменить.
          </p>
        </template>
        <p v-else>Удалить этот предмет?</p>
        <p v-if="deleteItemDialogError" class="mt-2 text-sm text-destructive">{{ deleteItemDialogError }}</p>
      </template>
    </ConfirmDialog>

    <ConfirmDialog
      :open="revokeDialogOpen"
      title="Отменить выдачу"
      confirm-label="Отменить выдачу"
      confirm-variant="destructive"
      :loading="revokingGrantId != null"
      @confirm="confirmRevokeGrant"
      @update:open="(v) => { if (!v) closeRevokeDialog(); }"
    >
      <template #description>
        <template v-if="grantPendingRevoke && selectedItem">
          <p>
            Отменить выдачу предмета
            <span class="font-medium text-foreground">«{{ selectedItem.name }}»</span>
            персонажу
            <span class="font-medium text-foreground">{{
              grantPendingRevoke.received_by_character?.name
                ?? `Персонаж #${grantPendingRevoke.received_by_character_id}`
            }}</span>?
          </p>
          <p class="mt-2">
            Если для предмета задано количество на складе, одна единица вернётся в хранилище.
          </p>
        </template>
        <p v-else>Отменить эту выдачу?</p>
        <p v-if="revokeDialogError" class="mt-2 text-sm text-destructive">{{ revokeDialogError }}</p>
      </template>
    </ConfirmDialog>
  </div>
</template>

