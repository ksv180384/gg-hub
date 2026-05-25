<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { Button, Tooltip } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { guildAuctionApi, type GuildAuctionLot } from '@/shared/api/guildAuctionApi';
import type { ApiError } from '@/shared/api/errors';
import { useGuildAuctionsSocket } from '@/shared/lib/useGuildAuctionsSocket';
import NotFoundPage from '@/pages/not-found/index.vue';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));
const lotId = computed(() => Number(route.params.lotId));
const socketGuildIds = computed(() => (Number.isFinite(guildId.value) && guildId.value > 0 ? [guildId.value] : []));

const loading = ref(true);
const error = ref('');
const accessNotFound = ref(false);
const lot = ref<GuildAuctionLot | null>(null);
const myPermissionSlugs = ref<string[]>([]);
const nowTs = ref(Date.now());
const closeDialogOpen = ref(false);
const closeSaving = ref(false);
let nowTimer: ReturnType<typeof window.setInterval> | null = null;

const canCloseLots = computed(() => myPermissionSlugs.value.includes('zakryvat-aukcion'));
const sortedBids = computed(() => [...(lot.value?.bids ?? [])].sort((a, b) => Date.parse(b.created_at) - Date.parse(a.created_at)));
const winnerName = computed(() => lot.value?.current_bid_character_name ?? lot.value?.winner_user_name ?? lot.value?.current_bid_user_name ?? 'Без победителя');

function formatNumber(value: number | null | undefined): string {
  return Number(value ?? 0).toLocaleString('ru-RU');
}

function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
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
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  if (days > 0) return `${days} д ${hours} ч`;
  if (hours > 0) return `${hours} ч ${String(minutes).padStart(2, '0')} мин`;
  return `${minutes} мин ${String(secs).padStart(2, '0')} сек`;
}

function statusLabel(status: GuildAuctionLot['status']): string {
  if (status === 'active') return 'Активен';
  if (status === 'closed') return 'Закрыт';
  return 'Отменен';
}

function closedByText(lot: GuildAuctionLot): string {
  if (lot.closed_by_character_name || lot.closed_by_user_name) {
    return lot.closed_by_character_name ?? lot.closed_by_user_name ?? '—';
  }
  if (lot.status === 'closed') return 'Закрыт автоматически';
  if (lot.status === 'cancelled') return 'Отменен';
  return 'Не закрыт';
}

function tierStyle(): string {
  const color = lot.value?.item?.tier?.color || '#64748b';
  return `background-color:${color};color:#fff`;
}

async function loadLot() {
  if (!guildId.value || !lotId.value || Number.isNaN(guildId.value) || Number.isNaN(lotId.value)) return;
  loading.value = true;
  error.value = '';
  accessNotFound.value = false;
  try {
    const [context, nextLot] = await Promise.all([
      guildAuctionApi.getContext(guildId.value),
      guildAuctionApi.getLot(guildId.value, lotId.value),
    ]);
    myPermissionSlugs.value = context.my_permission_slugs ?? [];
    lot.value = nextLot;
  } catch (e: unknown) {
    const status = (e as ApiError)?.status;
    if (status === 403 || status === 404) {
      accessNotFound.value = true;
    }
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить лот.';
    lot.value = null;
  } finally {
    loading.value = false;
  }
}

async function refreshLot() {
  try {
    lot.value = await guildAuctionApi.getLot(guildId.value, lotId.value);
  } catch {
    // best-effort realtime refresh
  }
}

async function confirmCloseLot() {
  if (!lot.value) return;
  closeSaving.value = true;
  try {
    lot.value = await guildAuctionApi.close(guildId.value, lot.value.id);
    closeDialogOpen.value = false;
  } finally {
    closeSaving.value = false;
  }
}

watch([guildId, lotId], loadLot, { immediate: true });

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
  onChanged: ({ guildId: eventGuildId, lotId: eventLotId }) => {
    if (eventGuildId !== guildId.value || eventLotId !== lotId.value) return;
    void refreshLot();
  },
});
</script>

<template>
  <NotFoundPage v-if="accessNotFound" />
  <div v-else class="container py-8 md:py-10">
    <div class="max-w-5xl">
      <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
          <RouterLink :to="{ name: 'guild-auction', params: { id: guildId } }" class="text-sm text-primary hover:underline">
            Назад к аукциону
          </RouterLink>
          <h1 class="mt-2 truncate text-2xl font-bold tracking-tight">
            {{ lot?.item?.name ?? `Лот #${lotId}` }}
          </h1>
          <p class="mt-1 text-sm text-muted-foreground">Полная информация по лоту, ставкам и выдаче предмета</p>
        </div>

        <Tooltip v-if="lot?.status === 'active' && canCloseLots" content="Закрыть лот" side="bottom">
          <Button type="button" variant="outline" size="icon" aria-label="Закрыть лот" @click="closeDialogOpen = true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </Button>
        </Tooltip>
      </div>

      <p v-if="loading" class="text-sm text-muted-foreground">Загрузка...</p>
      <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>

      <div v-else-if="lot" class="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.8fr)]">
        <section class="rounded-lg border border-border bg-card shadow-sm">
          <div class="border-b border-border px-4 py-3">
            <h2 class="font-semibold">Информация о лоте</h2>
          </div>
          <div class="grid gap-4 px-4 py-4 sm:grid-cols-2">
            <div>
              <div class="text-xs text-muted-foreground">Статус</div>
              <div class="mt-1 font-medium">{{ statusLabel(lot.status) }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">До конца</div>
              <div class="mt-1 font-medium">{{ lot.status === 'active' ? timeLeft(lot.ends_at) : formatDateTime(lot.closed_at) }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Предмет</div>
              <div class="mt-1 font-medium">{{ lot.item?.name ?? '—' }}</div>
              <div v-if="lot.item?.description" class="mt-1 text-sm text-muted-foreground">{{ lot.item.description }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Тип</div>
              <span v-if="lot.item?.tier" class="mt-1 inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold" :style="tierStyle()">
                {{ lot.item.tier.name }}
              </span>
              <div v-else class="mt-1 font-medium">—</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Остаток в хранилище</div>
              <div class="mt-1 font-medium">{{ lot.item?.quantity == null ? '∞' : formatNumber(lot.item.quantity) }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Стоимость из банка</div>
              <div class="mt-1 font-medium">{{ formatNumber(lot.item?.dkp_cost) }} ДКП</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Начальная ставка</div>
              <div class="mt-1 font-medium">{{ formatNumber(lot.start_price) }} ДКП</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Текущая ставка</div>
              <div class="mt-1 font-medium">{{ lot.current_bid_amount == null ? '—' : `${formatNumber(lot.current_bid_amount)} ДКП` }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">{{ lot.status === 'closed' ? 'Победитель' : 'Лидер' }}</div>
              <div class="mt-1 font-medium">{{ winnerName }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Выдача из хранилища</div>
              <div class="mt-1 font-medium">{{ lot.grant ? `#${lot.grant.id}` : '—' }}</div>
              <div v-if="lot.grant" class="text-xs text-muted-foreground">
                {{ lot.grant.received_by_character_name ?? 'персонаж не указан' }} · {{ formatNumber(lot.grant.dkp_charged) }} ДКП · {{ formatDateTime(lot.grant.granted_at) }}
              </div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Создан</div>
              <div class="mt-1 font-medium">{{ formatDateTime(lot.created_at) }}</div>
              <RouterLink
                v-if="lot.created_by_character_id"
                :to="{ name: 'guild-roster-member', params: { id: guildId, characterId: lot.created_by_character_id } }"
                class="text-xs text-muted-foreground hover:text-primary hover:underline"
              >
                {{ lot.created_by_character_name ?? lot.created_by_user_name ?? '—' }}
              </RouterLink>
              <div v-else class="text-xs text-muted-foreground">{{ lot.created_by_character_name ?? lot.created_by_user_name ?? '—' }}</div>
            </div>
            <div>
              <div class="text-xs text-muted-foreground">Закрыт</div>
              <div class="mt-1 font-medium">{{ formatDateTime(lot.closed_at) }}</div>
              <RouterLink
                v-if="lot.closed_by_character_id"
                :to="{ name: 'guild-roster-member', params: { id: guildId, characterId: lot.closed_by_character_id } }"
                class="text-xs text-muted-foreground hover:text-primary hover:underline"
              >
                {{ lot.closed_by_character_name ?? lot.closed_by_user_name ?? '—' }}
              </RouterLink>
              <div v-else class="text-xs text-muted-foreground">{{ closedByText(lot) }}</div>
            </div>
          </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
          <div class="border-b border-border px-4 py-3">
            <h2 class="font-semibold">История ставок</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">{{ sortedBids.length }} ставок</p>
          </div>
          <div v-if="sortedBids.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
            По этому лоту ставок пока нет.
          </div>
          <div v-else class="divide-y divide-border">
            <div v-for="bid in sortedBids" :key="bid.id" class="flex items-start gap-3 px-4 py-3">
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
                <div class="mt-0.5 truncate text-xs text-muted-foreground">{{ formatDateTime(bid.created_at) }}</div>
              </div>
            </div>
          </div>
        </section>
      </div>

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
  </div>
</template>
