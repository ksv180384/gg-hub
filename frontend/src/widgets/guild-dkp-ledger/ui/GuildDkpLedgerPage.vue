<script setup lang="ts">
import { computed, reactive } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Button, Input, Label, Select, Spinner, type SelectOption } from '@/shared/ui';
import { BackIconButton } from '@/shared/ui';
import { ResponsiveFiltersToolbar } from '@/widgets/responsive-filters-toolbar';
import { formatBankDateTime } from '@/features/guild-bank';
import { formatLedgerDescription, formatLedgerSourceLabel, useGuildDkpLedger } from '@/features/guild-dkp';

const router = useRouter();
const model = reactive(useGuildDkpLedger());

const eventTitleSelectOptions = computed<SelectOption[]>(() => [
  { value: model.eventTitleFilterAll, label: 'Все события' },
  ...model.eventTitleSelectOptions,
]);

const sourceSelectOptions = computed<SelectOption[]>(() => [
  { value: model.sourceFilterAll, label: 'Все источники' },
  ...model.ledgerSourceSelectOptions.map((option) => ({
    value: option.value,
    label: option.label,
  })),
]);

function goBackToBank() {
  router.push({ name: 'guild-bank', params: { id: String(model.guildId) } });
}
</script>

<template>
  <div class="container overflow-x-hidden py-8 md:py-12">
    <div class="min-w-0 w-full max-w-[816px] space-y-5">
      <div>
        <div class="flex flex-wrap items-center gap-2">
          <BackIconButton
            title="К хранилищу гильдии"
            aria-label="К хранилищу гильдии"
            @click="goBackToBank"
          />
          <h1 class="text-2xl font-bold tracking-tight">История ДКП</h1>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">
          Все начисления, списания и ручные корректировки очков гильдии
        </p>
      </div>

      <p v-if="model.loading && !model.hasLoadedOnce" class="text-sm text-muted-foreground">Загрузка…</p>
      <div
        v-else-if="model.error"
        class="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive"
      >
        {{ model.error }}
      </div>

      <template v-else-if="model.dkpEnabled">
        <ResponsiveFiltersToolbar
          v-model:name="model.userNameFilter"
          card-class="rounded-lg border-border/80 bg-background shadow-sm"
          card-content-class="p-4"
          name-label="Ник пользователя"
          name-placeholder="Поиск по нику"
          desktop-extra-filters-trigger
          desktop-row-class="flex-nowrap items-end"
          :extra-filters-active="model.ledgerExtraFiltersActive"
          :active-filters-count="model.ledgerActiveFiltersCount"
          extra-filters-title=""
          reset-button-title="Сбросить фильтр"
          reset-button-aria-label="Сбросить фильтр"
          name-mobile-input-id="guild-dkp-ledger-filter-user-mobile"
          name-desktop-input-id="guild-dkp-ledger-filter-user-desktop"
          desktop-name-wrap-class="min-w-[9rem] flex-1 basis-0 min-[480px]:min-w-[10rem]"
          @reset="model.resetLedgerFilters"
        >
          <template #extra-filters>
            <div class="grid gap-1.5">
              <Label for="guild-dkp-ledger-filter-from-mobile">Период с</Label>
              <Input
                id="guild-dkp-ledger-filter-from-mobile"
                v-model="model.occurredFromFilter"
                type="date"
                class="h-8 w-full"
              />
            </div>
            <div class="grid gap-1.5">
              <Label for="guild-dkp-ledger-filter-to-mobile">Период по</Label>
              <Input
                id="guild-dkp-ledger-filter-to-mobile"
                v-model="model.occurredToFilter"
                type="date"
                class="h-8 w-full"
              />
            </div>
            <div class="grid gap-1.5">
              <Label for="guild-dkp-ledger-filter-event-mobile">Событие</Label>
              <Select
                id="guild-dkp-ledger-filter-event-mobile"
                v-model="model.eventTitleFilter"
                :options="eventTitleSelectOptions"
                placeholder="Все события"
                trigger-class="h-8 w-full"
              />
            </div>
            <div class="grid gap-1.5">
              <Label for="guild-dkp-ledger-filter-source-mobile">Источник</Label>
              <Select
                id="guild-dkp-ledger-filter-source-mobile"
                v-model="model.sourceFilter"
                :options="sourceSelectOptions"
                placeholder="Все источники"
                trigger-class="h-8 w-full"
              />
            </div>
          </template>
          <template #desktop-filters>
            <div class="grid min-w-[9rem] flex-1 basis-0 gap-1.5 min-[480px]:min-w-[10rem]">
              <Label for="guild-dkp-ledger-filter-from-desktop">Период с</Label>
              <Input
                id="guild-dkp-ledger-filter-from-desktop"
                v-model="model.occurredFromFilter"
                type="date"
                class="h-8 w-full"
              />
            </div>
            <div class="grid min-w-[9rem] flex-1 basis-0 gap-1.5 min-[480px]:min-w-[10rem]">
              <Label for="guild-dkp-ledger-filter-to-desktop">Период по</Label>
              <Input
                id="guild-dkp-ledger-filter-to-desktop"
                v-model="model.occurredToFilter"
                type="date"
                class="h-8 w-full"
              />
            </div>
          </template>
          <template #desktop-extra-filters>
            <div class="grid gap-1.5">
              <Label for="guild-dkp-ledger-filter-event-desktop">Событие</Label>
              <Select
                id="guild-dkp-ledger-filter-event-desktop"
                v-model="model.eventTitleFilter"
                :options="eventTitleSelectOptions"
                placeholder="Все события"
                trigger-class="h-8 w-full"
              />
            </div>
            <div class="grid gap-1.5">
              <Label for="guild-dkp-ledger-filter-source-desktop">Источник</Label>
              <Select
                id="guild-dkp-ledger-filter-source-desktop"
                v-model="model.sourceFilter"
                :options="sourceSelectOptions"
                placeholder="Все источники"
                trigger-class="h-8 w-full"
              />
            </div>
          </template>
        </ResponsiveFiltersToolbar>

        <div class="relative min-h-[120px]">
          <div
            v-if="model.filtersLoading"
            class="absolute left-1/2 top-0 z-10 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-muted/70 px-2 py-0.5"
            aria-busy="true"
            aria-live="polite"
          >
            <Spinner class="h-3 w-3 shrink-0 text-muted-foreground" />
            <span class="text-xs text-muted-foreground">Загрузка…</span>
          </div>

          <p
            v-if="!model.filtersLoading && !model.entries.length"
            class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
          >
            {{ model.hasActiveFilters ? 'Ничего не найдено по заданным фильтрам.' : 'Пока нет движений ДКП.' }}
          </p>

          <ul v-else-if="model.entries.length" class="overflow-hidden rounded-lg border border-border bg-background shadow-sm">
            <li
              v-for="entry in model.entries"
              :key="entry.id"
              class="border-b border-border px-4 py-3 last:border-b-0"
            >
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                  <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="min-w-0 truncate text-sm font-medium text-foreground">
                      {{ formatLedgerDescription(entry) }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                      {{ formatLedgerSourceLabel(entry.source) }}
                    </span>
                  </div>
                  <div class="mt-1 text-xs text-muted-foreground">
                    {{ formatBankDateTime(entry.occurred_at) }}
                    <span v-if="entry.user?.name"> · {{ entry.user.name }}</span>
                    <span v-if="entry.actor_user?.name"> · инициатор: {{ entry.actor_user.name }}</span>
                  </div>
                </div>
                <div
                  class="inline-flex h-7 shrink-0 items-center justify-center rounded-md px-2.5 text-sm font-semibold tabular-nums"
                  :class="entry.amount >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-destructive/10 text-destructive'"
                >
                  {{ entry.amount >= 0 ? '+' : '' }}{{ entry.amount }}
                </div>
              </div>
            </li>
          </ul>

          <div
            v-if="model.canLoadMoreLedger || model.ledgerLoadingMore"
            class="flex flex-col items-center gap-2 pt-2"
          >
            <p v-if="model.ledgerTotal > 0" class="text-xs text-muted-foreground">
              Показано {{ model.entries.length }} из {{ model.ledgerTotal }}
            </p>
            <Button
              v-if="model.canLoadMoreLedger"
              variant="outline"
              class="h-9"
              :disabled="model.ledgerLoadingMore"
              @click="model.loadMoreLedger()"
            >
              {{ model.ledgerLoadingMore ? 'Загрузка…' : 'Загрузить ещё' }}
            </Button>
          </div>
        </div>
      </template>

      <RouterLink
        :to="{ name: 'guild-bank', params: { id: model.guildId } }"
        class="inline-flex text-sm text-primary hover:underline"
      >
        Вернуться в хранилище
      </RouterLink>
    </div>
  </div>
</template>
