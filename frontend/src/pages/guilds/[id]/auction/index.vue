<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import type { DateRange } from 'radix-vue';
import {
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'radix-vue';
import {
  Button,
  DateRangePicker,
  Input,
  Label,
  TableSortHead,
  Tooltip,
} from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { guildAuctionApi, type GuildAuctionLot } from '@/shared/api/guildAuctionApi';
import { guildBankApi, type GuildBankItem } from '@/shared/api/guildBankApi';
import type { ApiError } from '@/shared/api/errors';
import { useGuildAuctionsSocket } from '@/shared/lib/useGuildAuctionsSocket';
import { parseDkpCostInput } from '@/shared/lib/dkpValidation';
import NotFoundPage from '@/pages/not-found/index.vue';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));
const isHistory = computed(() => route.name === 'guild-auction-history');
const socketGuildIds = computed(() => (Number.isFinite(guildId.value) && guildId.value > 0 ? [guildId.value] : []));

const loading = ref(true);
const error = ref('');
const auctionAccessNotFound = ref(false);
const lots = ref<GuildAuctionLot[]>([]);
const closedLots = ref<GuildAuctionLot[]>([]);
const closedLotsLoading = ref(false);
const closedLotsError = ref('');
const closedLotsCount = ref(0);
const auctionBidsCount = ref(0);
const closedLotsPage = ref(1);
const closedLotsLastPage = ref(1);
const closedLotsTotal = ref(0);
const bankItems = ref<GuildBankItem[]>([]);
const myPermissionSlugs = ref<string[]>([]);
const dkpEnabled = ref(false);
const myDkpBalance = ref(0);
const myCharacters = ref<Array<{ id: number; name: string }>>([]);
const selectedLotId = ref<number | null>(null);
const nowTs = ref(Date.now());
let nowTimer: ReturnType<typeof window.setInterval> | null = null;
let closedLotsRequestId = 0;

const canAddLots = computed(() => myPermissionSlugs.value.includes('dobavliat-predmety-na-aukcion'));
const canCloseLots = computed(() => myPermissionSlugs.value.includes('zakryvat-aukcion'));
const closedLotsSort = ref<'asc' | 'desc'>('desc');
const closedDateRange = shallowRef<DateRange>({
  start: undefined,
  end: undefined,
});
const closedDateFrom = computed(
  () => closedDateRange.value.start?.toString().slice(0, 10) ?? '',
);
const closedDateTo = computed(
  () => closedDateRange.value.end?.toString().slice(0, 10) ?? '',
);
const activeLots = computed(() => lots.value.filter((lot) => lot.status === 'active'));
const displayedLots = computed(() => (isHistory.value ? closedLots.value : activeLots.value));
const emptyLotsText = computed(() => {
  if (!isHistory.value) return 'На аукционе пока нет активных лотов.';
  return closedLotsCount.value === 0
    ? 'История аукциона пока пуста.'
    : 'За выбранный период закрытых лотов нет.';
});
const selectedLot = computed(() => displayedLots.value.find((lot) => lot.id === selectedLotId.value) ?? null);
const selectedLotBids = computed(() => {
  const lot = selectedLot.value;
  if (!lot) return [];
  return lot.bids
    .map((bid) => ({ ...bid, itemName: lot.item?.name ?? `Лот #${lot.id}` }))
    .sort((a, b) => Date.parse(b.created_at) - Date.parse(a.created_at));
});

const stats = computed(() => [
  { key: 'lots', label: 'Активных лотов', value: activeLots.value.length },
  { key: 'bids', label: 'Ставок', value: auctionBidsCount.value },
  { key: 'closed', label: 'Закрыто', value: closedLotsCount.value },
]);

function formatNumber(value: number | null | undefined): string {
  return Number(value ?? 0).toLocaleString('ru-RU');
}

function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '';
  return new Date(iso).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function timeLeft(iso: string): string {
  const seconds = Math.max(0, Math.floor((Date.parse(iso) - nowTs.value) / 1000));
  if (seconds <= 0) return 'завершен';
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  if (hours > 0) return `${hours} ч ${String(minutes).padStart(2, '0')} мин`;
  return `${minutes} мин ${String(secs).padStart(2, '0')} сек`;
}

function minBid(lot: GuildAuctionLot): number {
  return Math.max(lot.start_price, (lot.current_bid_amount ?? 0) + 1);
}

function tierStyle(item: GuildAuctionLot['item'] | GuildBankItem | null): string {
  const color = item?.tier?.color || '#64748b';
  return `background-color:${color};color:#fff`;
}

function applyClosedLotsPage(page: Awaited<ReturnType<typeof guildAuctionApi.listClosedLots>>) {
  closedLots.value = page.lots;
  closedLotsError.value = '';
  closedLotsPage.value = page.currentPage;
  closedLotsLastPage.value = page.lastPage;
  closedLotsTotal.value = page.total;
  ensureSelectedLot();
}

async function loadClosedLots(page = 1) {
  if (!isHistory.value || !guildId.value) return;

  const requestId = ++closedLotsRequestId;
  closedLotsLoading.value = true;
  closedLotsError.value = '';

  try {
    const result = await guildAuctionApi.listClosedLots(guildId.value, {
      page,
      dateFrom: closedDateFrom.value,
      dateTo: closedDateTo.value,
      sort: closedLotsSort.value,
    });
    if (requestId !== closedLotsRequestId) return;
    applyClosedLotsPage(result);
  } catch (e: unknown) {
    if (requestId !== closedLotsRequestId) return;
    closedLots.value = [];
    closedLotsError.value = e instanceof Error
      ? e.message
      : 'Не удалось загрузить историю аукциона.';
    ensureSelectedLot();
  } finally {
    if (requestId === closedLotsRequestId) {
      closedLotsLoading.value = false;
    }
  }
}

async function loadPage() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  loading.value = true;
  error.value = '';
  auctionAccessNotFound.value = false;
  try {
    const historyRequest = isHistory.value
      ? guildAuctionApi.listClosedLots(guildId.value, {
          page: 1,
          dateFrom: closedDateFrom.value,
          dateTo: closedDateTo.value,
          sort: closedLotsSort.value,
        })
      : Promise.resolve(null);
    const [context, nextLots, items, historyPage] = await Promise.all([
      guildAuctionApi.getContext(guildId.value),
      guildAuctionApi.listLots(guildId.value),
      guildBankApi.listItems(guildId.value),
      historyRequest,
    ]);
    myPermissionSlugs.value = context.my_permission_slugs ?? [];
    dkpEnabled.value = context.dkp_enabled;
    myDkpBalance.value = context.my_dkp_balance ?? 0;
    myCharacters.value = context.my_characters ?? [];
    auctionBidsCount.value = context.auction_bids_count ?? 0;
    closedLotsCount.value = context.closed_lots_count ?? 0;
    lots.value = nextLots;
    bankItems.value = items;
    if (historyPage) {
      applyClosedLotsPage(historyPage);
    } else {
      ensureSelectedLot();
    }
  } catch (e: unknown) {
    const status = (e as ApiError)?.status;
    if (status === 403 || status === 404) {
      auctionAccessNotFound.value = true;
    }
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить аукцион.';
    lots.value = [];
    closedLots.value = [];
  } finally {
    loading.value = false;
  }
}

async function refreshLot() {
  try {
    const [nextLots, context] = await Promise.all([
      guildAuctionApi.listLots(guildId.value),
      guildAuctionApi.getContext(guildId.value),
    ]);
    lots.value = nextLots;
    myDkpBalance.value = context.my_dkp_balance ?? myDkpBalance.value;
    auctionBidsCount.value = context.auction_bids_count ?? auctionBidsCount.value;
    closedLotsCount.value = context.closed_lots_count ?? closedLotsCount.value;
    if (isHistory.value) {
      await loadClosedLots(closedLotsPage.value);
    } else {
      ensureSelectedLot();
    }
  } catch {
    // best-effort realtime refresh
  }
}

function ensureSelectedLot() {
  if (selectedLotId.value && displayedLots.value.some((lot) => lot.id === selectedLotId.value)) {
    return;
  }
  selectedLotId.value = displayedLots.value[0]?.id ?? null;
}

function selectLot(lot: GuildAuctionLot) {
  selectedLotId.value = lot.id;
}

function toggleClosedLotsSort() {
  closedLotsSort.value = closedLotsSort.value === 'desc' ? 'asc' : 'desc';
}

function changeClosedLotsPage(page: number) {
  if (page < 1 || page > closedLotsLastPage.value || page === closedLotsPage.value) return;
  void loadClosedLots(page);
}

watch(guildId, loadPage, { immediate: true });
watch(isHistory, (history) => {
  if (history) {
    void loadClosedLots(1);
  } else {
    ensureSelectedLot();
  }
});
watch([closedDateFrom, closedDateTo, closedLotsSort], () => {
  if (isHistory.value && !loading.value) {
    void loadClosedLots(1);
  }
});
onMounted(() => {
  nowTimer = window.setInterval(() => {
    nowTs.value = Date.now();
  }, 1000);
});

onUnmounted(() => {
  if (nowTimer !== null) {
    window.clearInterval(nowTimer);
    nowTimer = null;
  }
});

useGuildAuctionsSocket({
  guildIds: socketGuildIds,
  onChanged: ({ guildId: eventGuildId, lotId }) => {
    if (eventGuildId !== guildId.value) return;
    void refreshLot();
  },
});

const addDialogOpen = ref(false);
const addEndsAt = ref('');
const addRows = ref<Array<{ itemId: string; quantity: string; startPrice: string }>>([
  { itemId: '', quantity: '1', startPrice: '' },
]);
const addSaving = ref(false);
const addError = ref('');

const datetimeLocalMin = computed(() => {
  const d = new Date(Date.now() + 60 * 1000);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
});

function defaultEndsAt(): string {
  const d = new Date(Date.now() + 60 * 60 * 1000);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function openAddDialog() {
  addEndsAt.value = defaultEndsAt();
  addRows.value = [{ itemId: '', quantity: '1', startPrice: '' }];
  addError.value = '';
  addDialogOpen.value = true;
}

function closeAddDialog() {
  if (addSaving.value) return;
  addDialogOpen.value = false;
}

function addLotRow() {
  addRows.value.push({ itemId: '', quantity: '1', startPrice: '' });
}

function removeLotRow(index: number) {
  if (addRows.value.length <= 1) return;
  addRows.value.splice(index, 1);
}

function selectedBankItem(itemId: string): GuildBankItem | null {
  const id = Number(itemId);
  return bankItems.value.find((item) => item.id === id) ?? null;
}

async function submitAddLots() {
  addError.value = '';
  if (!addEndsAt.value) {
    addError.value = 'Укажите время окончания аукциона.';
    return;
  }

  const payloadLots = [];
  for (const row of addRows.value) {
    const itemId = Number(row.itemId);
    if (!itemId) {
      addError.value = 'Выберите предмет для каждого лота.';
      return;
    }

    const quantity = Number(row.quantity);
    if (!Number.isInteger(quantity) || quantity < 1) {
      addError.value = 'Количество должно быть целым положительным числом.';
      return;
    }

    const item = selectedBankItem(row.itemId);
    if (item && item.quantity !== null && quantity > item.quantity) {
      addError.value = `На складе недостаточно предметов «${item.name}».`;
      return;
    }

    let startPrice: number | null = null;
    if (row.startPrice.trim()) {
      startPrice = parseDkpCostInput(row.startPrice);
      if (startPrice === null) {
        addError.value = 'Начальная стоимость должна быть целым неотрицательным числом.';
        return;
      }
    }
    payloadLots.push({
      guild_bank_item_id: itemId,
      quantity,
      start_price: startPrice,
    });
  }

  addSaving.value = true;
  try {
    const created = await guildAuctionApi.createLots(guildId.value, {
      ends_at: new Date(addEndsAt.value).toISOString(),
      lots: payloadLots,
    });
    lots.value = [...created, ...lots.value].sort((a, b) => Date.parse(a.ends_at) - Date.parse(b.ends_at));
    selectedLotId.value = created[0]?.id ?? selectedLotId.value;
    addDialogOpen.value = false;
  } catch (e: unknown) {
    addError.value = e instanceof Error ? e.message : 'Не удалось выставить лоты.';
  } finally {
    addSaving.value = false;
  }
}

const bidDialogOpen = ref(false);
const bidLot = ref<GuildAuctionLot | null>(null);
const bidAmount = ref('');
const bidCharacterId = ref('');
const bidSaving = ref(false);
const bidError = ref('');

function openBidDialog(lot: GuildAuctionLot) {
  bidLot.value = lot;
  bidAmount.value = String(minBid(lot));
  bidCharacterId.value = myCharacters.value.length === 1 ? String(myCharacters.value[0]?.id ?? '') : '';
  bidError.value = '';
  bidDialogOpen.value = true;
}

function closeBidDialog() {
  if (bidSaving.value) return;
  bidDialogOpen.value = false;
  bidLot.value = null;
}

async function submitBid() {
  if (!bidLot.value) return;
  bidError.value = '';
  const amount = parseDkpCostInput(bidAmount.value);
  if (amount === null || amount < minBid(bidLot.value)) {
    bidError.value = `Минимальная ставка: ${formatNumber(minBid(bidLot.value))} ДКП.`;
    return;
  }
  const characterId = bidCharacterId.value ? Number(bidCharacterId.value) : null;
  if (myCharacters.value.length > 1 && !characterId) {
    bidError.value = 'Выберите персонажа, от имени которого делается ставка.';
    return;
  }

  bidSaving.value = true;
  try {
    const updated = await guildAuctionApi.bid(guildId.value, bidLot.value.id, amount, characterId);
    lots.value = lots.value.map((lot) => (lot.id === updated.id ? updated : lot));
    auctionBidsCount.value += 1;
    selectedLotId.value = updated.id;
    bidDialogOpen.value = false;
    bidLot.value = null;
  } catch (e: unknown) {
    bidError.value = e instanceof Error ? e.message : 'Не удалось сделать ставку.';
  } finally {
    bidSaving.value = false;
  }
}

const closeDialogOpen = ref(false);
const closeLot = ref<GuildAuctionLot | null>(null);
const closeSaving = ref(false);

function openCloseDialog(lot: GuildAuctionLot) {
  closeLot.value = lot;
  closeDialogOpen.value = true;
}

async function confirmCloseLot() {
  if (!closeLot.value) return;
  closeSaving.value = true;
  try {
    const updated = await guildAuctionApi.close(guildId.value, closeLot.value.id);
    lots.value = lots.value.map((lot) => (lot.id === updated.id ? updated : lot));
    selectedLotId.value = updated.id;
    const context = await guildAuctionApi.getContext(guildId.value);
    myDkpBalance.value = context.my_dkp_balance ?? myDkpBalance.value;
    auctionBidsCount.value = context.auction_bids_count ?? auctionBidsCount.value;
    closedLotsCount.value = context.closed_lots_count ?? closedLotsCount.value;
    closeDialogOpen.value = false;
    closeLot.value = null;
  } finally {
    closeSaving.value = false;
  }
}
</script>

<template>
  <NotFoundPage v-if="auctionAccessNotFound" />
  <div v-else class="container overflow-x-hidden py-8 md:py-10">
    <div class="max-w-7xl">
      <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
          <h1 class="text-2xl font-bold tracking-tight">
            {{ isHistory ? 'История аукциона' : 'Аукцион' }}
          </h1>
          <p class="mt-1 text-sm text-muted-foreground">
            {{ isHistory ? 'Закрытые лоты аукциона' : 'Лоты, ставки и выкуп предметов за ДКП' }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Tooltip content="Ваши доступные ДКП в этой гильдии" side="bottom">
            <span class="inline-flex h-9 cursor-help items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-700">
              <span class="size-2 rounded-full bg-emerald-500" aria-hidden="true" />
              ДКП: {{ formatNumber(myDkpBalance) }}
            </span>
          </Tooltip>
          <RouterLink
            :to="{ name: isHistory ? 'guild-auction' : 'guild-auction-history', params: { id: guildId } }"
            class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium shadow-sm hover:bg-accent"
          >
            {{ isHistory ? 'К аукциону' : 'История аукциона' }}
          </RouterLink>
          <Button v-if="canAddLots && !isHistory" type="button" @click="openAddDialog">+ Добавить лот</Button>
        </div>
      </div>

      <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
        <div v-for="stat in stats" :key="stat.key" class="flex items-center gap-4 rounded-lg border border-border bg-card px-4 py-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
            :class="{
              'bg-blue-50 text-blue-600': stat.key === 'lots',
              'bg-emerald-50 text-emerald-600': stat.key === 'bids',
              'bg-violet-50 text-violet-600': stat.key === 'closed',
            }"
          >
            <svg
              v-if="stat.key === 'lots'"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="size-5"
              aria-hidden="true"
            >
              <path d="M6 3h12l2 4v14H4V7l2-4Z" />
              <path d="M4 7h16" />
              <path d="M9 12h6" />
              <path d="M9 16h6" />
            </svg>
            <svg
              v-else-if="stat.key === 'bids'"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="size-5"
              aria-hidden="true"
            >
              <path d="M12 2v20" />
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6" />
            </svg>
            <svg
              v-else
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="size-5"
              aria-hidden="true"
            >
              <path d="M20 6 9 17l-5-5" />
            </svg>
          </div>
          <div class="min-w-0">
            <div class="text-xs font-medium text-muted-foreground">{{ stat.label }}</div>
            <div class="mt-1 text-xl font-bold tabular-nums tracking-tight">{{ formatNumber(stat.value) }}</div>
          </div>
        </div>
      </div>

      <p v-if="loading" class="text-sm text-muted-foreground">Загрузка...</p>
      <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
      <p v-else-if="!dkpEnabled" class="rounded-lg border border-border bg-card px-4 py-3 text-sm text-muted-foreground">
        Система ДКП отключена в этой гильдии.
      </p>

      <div v-else class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(360px,0.8fr)]">
        <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
          <div class="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="font-semibold">{{ isHistory ? 'Закрытые лоты' : 'Активные лоты' }}</h2>
              <p class="mt-0.5 text-xs text-muted-foreground">
                {{ isHistory ? 'Архив завершенных аукционов' : 'Ставка в последнюю минуту продлевает лот на 10 минут' }}
              </p>
            </div>
            <div
              v-if="isHistory"
              class="w-full sm:w-60"
            >
              <DateRangePicker v-model="closedDateRange" />
            </div>
          </div>

          <div v-if="isHistory && closedLotsLoading" class="px-4 py-8 text-center text-sm text-muted-foreground">
            Загрузка...
          </div>
          <div v-else-if="isHistory && closedLotsError" class="px-4 py-8 text-center text-sm text-destructive">
            {{ closedLotsError }}
          </div>
          <div v-else-if="displayedLots.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
            {{ emptyLotsText }}
          </div>

          <div v-else class="overflow-hidden">
            <table class="w-full table-fixed text-sm">
              <thead class="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                <tr>
                  <th class="w-[22%] px-4 py-3 text-left">Предмет</th>
                  <th class="w-[7%] px-2 py-3 text-left">Тип</th>
                  <th class="w-[9%] px-2 py-3 text-right">Кол-во</th>
                  <th class="w-[11%] px-2 py-3 text-right">Начальная</th>
                  <th class="w-[9%] px-2 py-3 text-right">Ставка</th>
                  <th class="w-[13%] px-2 py-3 text-left">{{ isHistory ? 'Победитель' : 'Лидер' }}</th>
                  <TableSortHead
                    v-if="isHistory"
                    active
                    :direction="closedLotsSort"
                    class="w-[14%] px-0 normal-case tracking-normal"
                    @click="toggleClosedLotsSort"
                  >
                    Дата закрытия
                  </TableSortHead>
                  <th v-else class="w-[14%] px-2 py-3 text-left">До конца</th>
                  <th v-if="!isHistory" class="w-[15%] px-4 py-3 text-right">Действия</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="lot in displayedLots"
                  :key="lot.id"
                  class="cursor-pointer border-b border-border last:border-b-0"
                  :class="[
                    lot.id === selectedLotId && 'bg-primary/5 ring-1 ring-inset ring-primary/20',
                    lot.status === 'active' ? 'hover:bg-muted/30' : 'bg-muted/20 text-muted-foreground',
                  ]"
                  @click="selectLot(lot)"
                >
                  <td class="px-4 py-3">
                    <RouterLink
                      :to="{ name: 'guild-auction-lot', params: { id: guildId, lotId: lot.id } }"
                      class="block truncate font-medium text-foreground hover:text-primary hover:underline"
                      @click.stop
                    >
                      {{ lot.item?.name ?? `Лот #${lot.id}` }}
                    </RouterLink>
                    <div class="text-xs text-muted-foreground">из хранилища</div>
                  </td>
                  <td class="px-2 py-3">
                    <span
                      v-if="lot.item?.tier"
                      class="inline-flex h-6 min-w-6 items-center justify-center rounded-md px-2 text-xs font-semibold"
                      :style="tierStyle(lot.item)"
                    >
                      {{ lot.item.tier.name.slice(0, 1).toUpperCase() }}
                    </span>
                    <span v-else class="text-muted-foreground">—</span>
                  </td>
                  <td class="px-2 py-3 text-right tabular-nums">{{ formatNumber(lot.quantity) }}</td>
                  <td class="px-2 py-3 text-right tabular-nums">{{ formatNumber(lot.start_price) }}</td>
                  <td class="px-2 py-3 text-right font-semibold tabular-nums text-foreground">
                    {{ lot.current_bid_amount == null ? '—' : formatNumber(lot.current_bid_amount) }}
                  </td>
                  <td class="truncate px-2 py-3">
                    {{ lot.current_bid_character_name ?? lot.current_bid_user_name ?? lot.winner_user_name ?? '—' }}
                  </td>
                  <td class="px-2 py-3">
                    <span v-if="lot.status === 'active'" class="font-medium text-foreground">{{ timeLeft(lot.ends_at) }}</span>
                    <span v-else>{{ formatDateTime(lot.closed_at) }}</span>
                  </td>
                  <td v-if="!isHistory" class="px-2 py-3">
                    <div class="flex flex-wrap justify-end gap-1.5">
                      <Tooltip v-if="lot.status === 'active'" content="Сделать ставку" side="top">
                        <Button
                          size="sm"
                          variant="outlinePrimary"
                          aria-label="Сделать ставку"
                          @click.stop="openBidDialog(lot)"
                        >
                          <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                          >
                            <path d="M12 2v20" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6" />
                          </svg>
                          Ставка
                        </Button>
                      </Tooltip>
                      <Tooltip v-if="lot.status === 'active' && canCloseLots" content="Закрыть лот" side="top">
                        <Button
                          size="sm"
                          variant="outline"
                          aria-label="Закрыть лот"
                          @click.stop="openCloseDialog(lot)"
                        >
                          <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                          >
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                          </svg>
                          Закрыть
                        </Button>
                      </Tooltip>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          <footer
            v-if="isHistory && closedLotsLastPage > 1"
            class="flex items-center justify-center gap-3 border-t border-border px-4 py-3"
          >
            <Button
              type="button"
              variant="outline"
              size="icon"
              :disabled="closedLotsLoading || closedLotsPage <= 1"
              title="Предыдущая страница"
              aria-label="Предыдущая страница"
              @click="changeClosedLotsPage(closedLotsPage - 1)"
            >
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-4"
                aria-hidden="true"
              >
                <path d="m15 18-6-6 6-6" />
              </svg>
            </Button>
            <span class="min-w-32 text-center text-sm text-muted-foreground">
              {{ closedLotsPage }} из {{ closedLotsLastPage }}
              <span class="sr-only">, всего {{ closedLotsTotal }}</span>
            </span>
            <Button
              type="button"
              variant="outline"
              size="icon"
              :disabled="closedLotsLoading || closedLotsPage >= closedLotsLastPage"
              title="Следующая страница"
              aria-label="Следующая страница"
              @click="changeClosedLotsPage(closedLotsPage + 1)"
            >
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-4"
                aria-hidden="true"
              >
                <path d="m9 18 6-6-6-6" />
              </svg>
            </Button>
          </footer>
          </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
          <div class="border-b border-border px-4 py-3">
            <h2 class="font-semibold">История ставок</h2>
            <p v-if="selectedLot" class="mt-0.5 truncate text-xs text-muted-foreground">
              {{ selectedLot.item?.name ?? `Лот #${selectedLot.id}` }}
            </p>
          </div>
          <div v-if="!selectedLot" class="px-4 py-8 text-center text-sm text-muted-foreground">
            Выберите лот, чтобы посмотреть ставки.
          </div>
          <div v-else-if="selectedLotBids.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
            По этому лоту ставок пока нет.
          </div>
          <div v-else class="divide-y divide-border">
            <div v-for="bid in selectedLotBids" :key="bid.id" class="flex items-start gap-3 px-4 py-3">
              <img
                v-if="bid.character_avatar_url"
                :src="bid.character_avatar_url"
                :alt="bid.character_name ?? bid.user_name ?? ''"
                class="h-8 w-8 shrink-0 rounded-md object-cover"
                loading="lazy"
              >
              <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary text-xs font-semibold text-primary-foreground">
                {{ (bid.character_name ?? bid.user_name ?? '?').slice(0, 1).toUpperCase() }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                  <RouterLink
                    v-if="bid.character_id"
                    :to="{ name: 'guild-roster-member', params: { id: guildId, characterId: bid.character_id } }"
                    class="truncate text-sm font-medium text-foreground hover:text-primary hover:underline"
                  >
                    {{ bid.character_name ?? bid.user_name ?? 'Пользователь' }}
                  </RouterLink>
                  <div v-else class="truncate text-sm font-medium">{{ bid.character_name ?? bid.user_name ?? 'Пользователь' }}</div>
                  <div class="shrink-0 text-sm font-semibold tabular-nums">{{ formatNumber(bid.amount) }} ДКП</div>
                </div>
                <div class="mt-0.5 truncate text-xs text-muted-foreground">
                  {{ bid.itemName }} · {{ formatDateTime(bid.created_at) }}
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <DialogRoot v-model:open="addDialogOpen">
      <ClientOnly>
        <DialogPortal>
          <DialogOverlay class="fixed inset-0 z-[3] bg-black/70" />
          <DialogContent class="fixed left-1/2 top-1/2 z-[4] max-h-[90vh] w-[min(720px,calc(100vw-24px))] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg border bg-background p-6 shadow-lg focus:outline-none">
            <DialogTitle class="text-lg font-semibold">Добавить лоты</DialogTitle>
            <form class="mt-4 space-y-4" @submit.prevent="submitAddLots">
              <div class="space-y-2">
                <Label for="auction-ends-at">Окончание аукциона</Label>
                <Input id="auction-ends-at" v-model="addEndsAt" type="datetime-local" :min="datetimeLocalMin" />
              </div>

              <div class="space-y-3">
                <div v-for="(row, index) in addRows" :key="index" class="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[minmax(0,1fr)_110px_160px_auto]">
                  <div class="space-y-2">
                    <Label>Предмет</Label>
                    <select v-model="row.itemId" class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                      <option value="">Выберите предмет</option>
                      <option v-for="item in bankItems" :key="item.id" :value="String(item.id)">
                        {{ item.name }} · банк: {{ item.dkp_cost ?? 0 }} ДКП · остаток: {{ item.quantity == null ? '∞' : item.quantity }}
                      </option>
                    </select>
                  </div>
                  <div class="space-y-2">
                    <Label>Количество</Label>
                    <Input
                      v-model="row.quantity"
                      type="number"
                      min="1"
                      :max="selectedBankItem(row.itemId)?.quantity ?? undefined"
                      inputmode="numeric"
                    />
                  </div>
                  <div class="space-y-2">
                    <Label>Начальная цена</Label>
                    <Input
                      v-model="row.startPrice"
                      inputmode="numeric"
                      :placeholder="String(selectedBankItem(row.itemId)?.dkp_cost ?? 0)"
                    />
                  </div>
                  <div class="flex items-end justify-end">
                    <Button type="button" variant="outline" size="icon" :disabled="addRows.length <= 1" aria-label="Удалить лот" @click="removeLotRow(index)">
                      ×
                    </Button>
                  </div>
                </div>
              </div>

              <Button type="button" variant="outline" size="sm" @click="addLotRow">+ Еще лот</Button>
              <p v-if="addError" class="text-sm text-destructive">{{ addError }}</p>
              <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" :disabled="addSaving" @click="closeAddDialog">Отмена</Button>
                <Button type="submit" :disabled="addSaving">{{ addSaving ? 'Выставление...' : 'Выставить' }}</Button>
              </div>
            </form>
          </DialogContent>
        </DialogPortal>
      </ClientOnly>
    </DialogRoot>

    <DialogRoot v-model:open="bidDialogOpen">
      <ClientOnly>
        <DialogPortal>
          <DialogOverlay class="fixed inset-0 z-[3] bg-black/70" />
          <DialogContent class="fixed left-1/2 top-1/2 z-[4] w-[min(420px,calc(100vw-24px))] -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 shadow-lg focus:outline-none">
            <DialogTitle class="text-lg font-semibold">Сделать ставку</DialogTitle>
            <form v-if="bidLot" class="mt-4 space-y-4" @submit.prevent="submitBid">
              <div class="rounded-lg border border-border bg-muted/30 px-3 py-2">
                <div class="font-medium">{{ bidLot.item?.name }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                  Минимум: {{ formatNumber(minBid(bidLot)) }} ДКП · доступно: {{ formatNumber(myDkpBalance) }} ДКП
                </div>
              </div>
              <div class="space-y-2">
                <Label for="bid-amount">Ставка ДКП</Label>
                <Input id="bid-amount" v-model="bidAmount" inputmode="numeric" />
              </div>
              <div v-if="myCharacters.length > 1" class="space-y-2">
                <Label for="bid-character">Персонаж</Label>
                <select
                  id="bid-character"
                  v-model="bidCharacterId"
                  class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                >
                  <option value="">Выберите персонажа</option>
                  <option v-for="character in myCharacters" :key="character.id" :value="String(character.id)">
                    {{ character.name }}
                  </option>
                </select>
              </div>
              <p v-if="bidError" class="text-sm text-destructive">{{ bidError }}</p>
              <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" :disabled="bidSaving" @click="closeBidDialog">Отмена</Button>
                <Button type="submit" :disabled="bidSaving">{{ bidSaving ? 'Ставка...' : 'Сделать ставку' }}</Button>
              </div>
            </form>
          </DialogContent>
        </DialogPortal>
      </ClientOnly>
    </DialogRoot>

    <ConfirmDialog
      v-model:open="closeDialogOpen"
      title="Закрыть лот?"
      description="Победитель получит предмет, а сумма ставки будет списана из его ДКП."
      confirm-label="Закрыть"
      cancel-label="Отмена"
      :loading="closeSaving"
      @confirm="confirmCloseLot"
    />
  </div>
</template>
