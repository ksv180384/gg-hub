import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import {
  guildBankApi,
  type GuildBankItem,
  type GuildBankGrant,
  type GuildBankGrantDkpConfirmation,
  type GuildBankItemTier,
} from '@/shared/api/guildBankApi';
import { guildsApi, type GuildRosterMember } from '@/shared/api/guildsApi';
import type { ApiError } from '@/shared/api/errors';
import { parseDkpCostInput } from '@/shared/lib/dkpValidation';

export const MAX_BANK_QUANTITY = 1_000_000_000;

export type GuildBankItemForm = {
  name: string;
  description: string;
  guild_bank_item_tier_id: string;
  dkp_cost: string;
  quantity: string;
};

export type GuildBankTierForm = {
  name: string;
  color: string;
};

export type GuildBankGrantForm = {
  received_by_character_id: string;
  reason: string;
};

export function formatBankDateTime(iso: string | null | undefined): string {
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

export function itemHasActiveGrants(item: GuildBankItem): boolean {
  return (item.grants_count ?? 0) > 0;
}

function mergeGuildBankItem(previous: GuildBankItem, next: GuildBankItem): GuildBankItem {
  return {
    ...next,
    grants_count: next.grants_count ?? previous.grants_count ?? 0,
    last_granted_at: next.last_granted_at ?? previous.last_granted_at,
  };
}

function resolveItemTierId(item: GuildBankItem): number | null {
  return item.guild_bank_item_tier_id ?? item.tier?.id ?? null;
}

function buildTierItemCounts(items: GuildBankItem[]): Map<number, number> {
  const counts = new Map<number, number>();
  for (const item of items) {
    const tierId = resolveItemTierId(item);
    if (tierId == null) continue;
    counts.set(tierId, (counts.get(tierId) ?? 0) + 1);
  }
  return counts;
}

function syncTierItemCounts(
  tiersList: GuildBankItemTier[],
  itemsList: GuildBankItem[],
): GuildBankItemTier[] {
  const counts = buildTierItemCounts(itemsList);
  return tiersList.map((tier) => ({
    ...tier,
    items_count: counts.get(tier.id) ?? 0,
  }));
}

export function useGuildBank() {
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
  const dkpLedgerAvailable = ref(false);
  const myDkpBalance = ref<number | null>(null);
  const grantNegativeConfirmOpen = ref(false);
  const grantNegativeConfirmInfo = ref<GuildBankGrantDkpConfirmation | null>(null);

  const tiers = ref<GuildBankItemTier[]>([]);
  const tiersLoading = ref(false);
  const tiersError = ref('');
  const tiersModalOpen = ref(false);
  const tierFormSaving = ref(false);
  const tierFormError = ref('');
  const tierForm = ref<GuildBankTierForm>({ name: '', color: '' });
  const tierEditing = ref<GuildBankItemTier | null>(null);
  const tierPendingDelete = ref<GuildBankItemTier | null>(null);
  const deleteTierDialogOpen = ref(false);
  const deleteTierDialogError = ref('');
  const deletingTierId = ref<number | null>(null);

  const itemFormOpen = ref(false);
  const itemFormSaving = ref(false);
  const itemFormError = ref('');
  const itemEditing = ref<GuildBankItem | null>(null);
  const itemForm = ref<GuildBankItemForm>({
    name: '',
    description: '',
    guild_bank_item_tier_id: '',
    dkp_cost: '',
    quantity: '',
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

  const grantForm = ref<GuildBankGrantForm>({
    received_by_character_id: '',
    reason: '',
  });

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

  async function loadPageContext() {
    if (!guildId.value) return;
    try {
      const context = await guildBankApi.getPageContext(guildId.value);
      myPermissionSlugs.value = context.my_permission_slugs ?? [];
      dkpEnabled.value = context.dkp_enabled ?? false;
      dkpLedgerAvailable.value = context.dkp_ledger_available ?? context.dkp_enabled ?? false;
      myDkpBalance.value = context.my_dkp_balance ?? null;
    } catch {
      myPermissionSlugs.value = [];
      dkpEnabled.value = false;
      dkpLedgerAvailable.value = false;
      myDkpBalance.value = null;
    }
  }

  async function ensureTiersLoaded() {
    if (tiersLoading.value || tiers.value.length) return;
    await loadTiers();
  }

  async function loadTiers() {
    if (!guildId.value) return;
    tiersLoading.value = true;
    tiersError.value = '';
    try {
      tiers.value = syncTierItemCounts(await guildBankApi.listTiers(guildId.value), items.value);
    } catch (e: unknown) {
      tiers.value = [];
      tiersError.value = e instanceof Error ? e.message : 'Не удалось загрузить тиры.';
    } finally {
      tiersLoading.value = false;
    }
  }

  async function loadItems() {
    if (!guildId.value) return;
    loading.value = true;
    error.value = '';
    try {
      items.value = await guildBankApi.listItems(guildId.value);
      tiers.value = syncTierItemCounts(tiers.value, items.value);
      itemsSearch.value = '';
      if (selectedItemId.value == null && items.value.length) {
        selectedItemId.value = items.value[0]?.id ?? null;
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
      const grantedBy = (g.granted_by_character?.name ?? '').toLowerCase();
      const id = String(g.received_by_character_id);
      return name.includes(q) || grantedBy.includes(q) || id.includes(q);
    });
  });

  const totalItemsCount = computed(() => items.value.length);
  const totalStockCount = computed(() =>
    items.value.reduce((sum, item) => sum + (item.quantity ?? 0), 0),
  );
  const totalGrantsCount = computed(() =>
    items.value.reduce((sum, item) => sum + (item.grants_count ?? 0), 0),
  );
  const tiersCount = computed(() => tiers.value.length);

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

  function openTiersModal() {
    tierForm.value = { name: '', color: '' };
    tierEditing.value = null;
    tierFormError.value = '';
    deleteTierDialogError.value = '';
    tiersModalOpen.value = true;
    tiers.value = syncTierItemCounts(tiers.value, items.value);
    if (!tiers.value.length && !tiersLoading.value) {
      void loadTiers();
    }
  }

  async function createTier() {
    if (!guildId.value) return;
    tierFormError.value = '';
    const name = tierForm.value.name.trim();
    const color = tierForm.value.color.trim();
    if (!name) {
      tierFormError.value = 'Укажите название тира.';
      return;
    }
    if (!color) {
      tierFormError.value = 'Укажите цвет тира.';
      return;
    }
    tierFormSaving.value = true;
    try {
      const created = await guildBankApi.createTier(guildId.value, { name, color });
      tiers.value = syncTierItemCounts(
        [...tiers.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ru')),
        items.value,
      );
      tierForm.value = { name: '', color: '' };
    } catch (e: unknown) {
      tierFormError.value = e instanceof Error ? e.message : 'Не удалось добавить тир.';
    } finally {
      tierFormSaving.value = false;
    }
  }

  function openEditTier(tier: GuildBankItemTier) {
    tierEditing.value = tier;
    tierForm.value = {
      name: tier.name,
      color: tier.color ?? '',
    };
    tierFormError.value = '';
  }

  function cancelEditTier() {
    if (tierFormSaving.value) return;
    tierEditing.value = null;
    tierForm.value = { name: '', color: '' };
    tierFormError.value = '';
  }

  async function saveTier() {
    if (!guildId.value) return;
    if (!tierEditing.value) {
      await createTier();
      return;
    }

    tierFormError.value = '';
    const name = tierForm.value.name.trim();
    const color = tierForm.value.color.trim();
    if (!name) {
      tierFormError.value = 'Укажите название тира.';
      return;
    }
    if (!color) {
      tierFormError.value = 'Укажите цвет тира.';
      return;
    }

    const tierId = tierEditing.value.id;
    tierFormSaving.value = true;
    try {
      const updated = await guildBankApi.updateTier(guildId.value, tierId, { name, color });
      tiers.value = syncTierItemCounts(
        tiers.value
          .map((tier) => (tier.id === updated.id ? updated : tier))
          .sort((a, b) => a.name.localeCompare(b.name, 'ru')),
        items.value,
      );
      items.value = items.value.map((item) =>
        item.guild_bank_item_tier_id === updated.id
          ? { ...item, tier: { ...updated } }
          : item
      );
      tierEditing.value = null;
      tierForm.value = { name: '', color: '' };
    } catch (e: unknown) {
      tierFormError.value = e instanceof Error ? e.message : 'Не удалось сохранить тир.';
    } finally {
      tierFormSaving.value = false;
    }
  }

  function openDeleteTierDialog(tier: GuildBankItemTier) {
    if ((tier.items_count ?? 0) > 0) return;
    tierPendingDelete.value = tier;
    deleteTierDialogError.value = '';
    deleteTierDialogOpen.value = true;
  }

  function closeDeleteTierDialog() {
    if (deletingTierId.value != null) return;
    deleteTierDialogOpen.value = false;
    tierPendingDelete.value = null;
    deleteTierDialogError.value = '';
  }

  async function confirmDeleteTier() {
    const tier = tierPendingDelete.value;
    if (!tier || !guildId.value) return;
    deletingTierId.value = tier.id;
    deleteTierDialogError.value = '';
    try {
      await guildBankApi.deleteTier(guildId.value, tier.id);
      tiers.value = tiers.value.filter((t) => t.id !== tier.id);
      items.value = items.value.map((item) =>
        item.guild_bank_item_tier_id === tier.id
          ? { ...item, guild_bank_item_tier_id: null, tier: null }
          : item
      );
      tiers.value = syncTierItemCounts(tiers.value, items.value);
      deleteTierDialogOpen.value = false;
      tierPendingDelete.value = null;
    } catch (e: unknown) {
      deleteTierDialogError.value = e instanceof Error ? e.message : 'Не удалось удалить тир.';
    } finally {
      deletingTierId.value = null;
    }
  }

  function openCreateItem() {
    itemEditing.value = null;
    itemForm.value = { name: '', description: '', guild_bank_item_tier_id: '', dkp_cost: '', quantity: '' };
    itemFormError.value = '';
    itemFormOpen.value = true;
    void ensureTiersLoaded();
  }

  function openEditItem(item: GuildBankItem) {
    itemEditing.value = item;
    itemForm.value = {
      name: item.name,
      description: item.description ?? '',
      guild_bank_item_tier_id: item.guild_bank_item_tier_id == null ? '' : String(item.guild_bank_item_tier_id),
      dkp_cost: item.dkp_cost == null ? '' : String(item.dkp_cost),
      quantity: item.quantity == null ? '' : String(item.quantity),
    };
    itemFormError.value = '';
    itemFormOpen.value = true;
    void ensureTiersLoaded();
  }

  async function saveItem() {
    if (!guildId.value) return;
    itemFormError.value = '';
    const name = itemForm.value.name.trim();
    if (!name) {
      itemFormError.value = 'Введите название предмета.';
      return;
    }
    let parsedDkpCost: number | null = null;
    if (dkpEnabled.value && itemForm.value.dkp_cost.trim()) {
      parsedDkpCost = parseDkpCostInput(itemForm.value.dkp_cost);
      if (parsedDkpCost === null) {
        itemFormError.value = 'Стоимость ДКП должна быть целым неотрицательным числом.';
        return;
      }
    }

    itemFormSaving.value = true;
    try {
      const tierId = Number(itemForm.value.guild_bank_item_tier_id);
      const payload = {
        name,
        description: itemForm.value.description.trim() || null,
        guild_bank_item_tier_id: tierId > 0 ? tierId : null,
        dkp_cost: parsedDkpCost,
        quantity: itemForm.value.quantity.trim() !== ''
          ? Number(itemForm.value.quantity.trim())
          : null,
      };
      if (itemEditing.value) {
        const updated = await guildBankApi.updateItem(guildId.value, itemEditing.value.id, payload);
        items.value = items.value.map((i) => (i.id === updated.id ? mergeGuildBankItem(i, updated) : i));
      } else {
        const created = await guildBankApi.createItem(guildId.value, payload);
        items.value = [...items.value, created].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
        selectedItemId.value = created.id;
        await loadGrants(created.id);
      }
      tiers.value = syncTierItemCounts(tiers.value, items.value);
      itemFormOpen.value = false;
    } catch (e: unknown) {
      itemFormError.value = e instanceof Error ? e.message : 'Не удалось сохранить предмет.';
    } finally {
      itemFormSaving.value = false;
    }
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
      items.value = items.value.map((i) => (i.id === updated.id ? mergeGuildBankItem(i, updated) : i));
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
      tiers.value = syncTierItemCounts(tiers.value, items.value);
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
    if (rosterMembers.value.length === 0) {
      void loadRosterMembers();
    }
  }

  async function saveGrant(confirmNegativeBalance = false) {
    if (!guildId.value || !selectedItem.value) return;
    grantFormError.value = '';
    if (rosterMembers.value.length === 0) {
      await loadRosterMembers();
    }
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
        confirm_negative_balance: confirmNegativeBalance,
      });
      grants.value = [created, ...grants.value];
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
      grantNegativeConfirmOpen.value = false;
      grantNegativeConfirmInfo.value = null;
      if (dkpEnabled.value) {
        await loadPageContext();
      }
    } catch (e: unknown) {
      const err = e as Error & { dkpConfirmation?: GuildBankGrantDkpConfirmation };
      if (err.dkpConfirmation?.requires_confirmation) {
        grantNegativeConfirmInfo.value = err.dkpConfirmation;
        grantNegativeConfirmOpen.value = true;
        grantFormError.value = '';
        return;
      }
      grantFormError.value = err instanceof Error ? err.message : 'Не удалось выдать предмет.';
    } finally {
      grantFormSaving.value = false;
    }
  }

  function closeGrantNegativeConfirm() {
    if (grantFormSaving.value) return;
    grantNegativeConfirmOpen.value = false;
    grantNegativeConfirmInfo.value = null;
  }

  async function confirmGrantWithNegativeBalance() {
    await saveGrant(true);
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
      if (dkpEnabled.value) {
        await loadPageContext();
      }
    } catch (e: unknown) {
      revokeDialogError.value =
        e instanceof Error ? e.message : 'Не удалось отменить выдачу.';
    } finally {
      revokingGrantId.value = null;
    }
  }

  onMounted(async () => {
    await Promise.all([loadPageContext(), loadItems()]);
    if (selectedItemId.value) {
      await loadGrants(selectedItemId.value);
    }
  });

  return {
    guildId,
    loading,
    error,
    items,
    selectedItemId,
    itemsSearch,
    grantsLoading,
    grantsError,
    grants,
    grantsSearch,
    canAddItems,
    canDeleteItems,
    canGrantItems,
    dkpEnabled,
    dkpLedgerAvailable,
    myDkpBalance,
    grantNegativeConfirmOpen,
    grantNegativeConfirmInfo,
    tiers,
    tiersLoading,
    tiersError,
    tiersModalOpen,
    tierForm,
    tierEditing,
    tierFormSaving,
    tierFormError,
    tierPendingDelete,
    deleteTierDialogOpen,
    deleteTierDialogError,
    deletingTierId,
    itemFormOpen,
    itemFormSaving,
    itemFormError,
    itemEditing,
    itemForm,
    grantFormOpen,
    grantFormSaving,
    grantFormError,
    revokingGrantId,
    revokeDialogOpen,
    grantPendingRevoke,
    revokeDialogError,
    deleteItemDialogOpen,
    itemPendingDelete,
    deleteItemDialogError,
    deletingItemId,
    restockModalOpen,
    restockItem,
    restockAmount,
    restockSaving,
    restockError,
    grantForm,
    rosterLoading,
    rosterError,
    memberSelectOpen,
    memberSearch,
    selectedRosterMember,
    filteredRosterMembers,
    selectedItem,
    filteredItems,
    filteredGrants,
    totalItemsCount,
    totalStockCount,
    totalGrantsCount,
    tiersCount,
    selectItem,
    openTiersModal,
    createTier,
    openEditTier,
    cancelEditTier,
    saveTier,
    openDeleteTierDialog,
    closeDeleteTierDialog,
    confirmDeleteTier,
    openCreateItem,
    openEditItem,
    saveItem,
    openRestockModal,
    closeRestockModal,
    saveRestock,
    openDeleteItemDialog,
    closeDeleteItemDialog,
    confirmDeleteItem,
    openGrant,
    saveGrant,
    closeGrantNegativeConfirm,
    confirmGrantWithNegativeBalance,
    openRevokeGrantDialog,
    closeRevokeDialog,
    confirmRevokeGrant,
  };
}
