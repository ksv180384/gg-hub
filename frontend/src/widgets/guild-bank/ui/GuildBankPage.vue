<script setup lang="ts">
import { computed, reactive } from 'vue';
import { RouterLink } from 'vue-router';
import { useGuildBank } from '@/features/guild-bank';
import { Tooltip } from '@/shared/ui';
import GuildBankItemsCard from './GuildBankItemsCard.vue';
import GuildBankGrantsCard from './GuildBankGrantsCard.vue';
import GuildBankItemFormDialog from './GuildBankItemFormDialog.vue';
import GuildBankRestockDialog from './GuildBankRestockDialog.vue';
import GuildBankGrantFormDialog from './GuildBankGrantFormDialog.vue';
import GuildBankDeleteItemDialog from './GuildBankDeleteItemDialog.vue';
import GuildBankRevokeGrantDialog from './GuildBankRevokeGrantDialog.vue';
import GuildBankTiersDialog from './GuildBankTiersDialog.vue';
import GuildBankGrantDkpConfirmDialog from './GuildBankGrantDkpConfirmDialog.vue';

const model = reactive(useGuildBank());

const stats = computed(() => [
  { key: 'items', label: 'Предметов', value: model.totalItemsCount },
  { key: 'stock', label: 'Остаток', value: model.totalStockCount },
  { key: 'grants', label: 'Выдано', value: model.totalGrantsCount },
]);
</script>

<template>
  <div class="container overflow-x-hidden py-8 md:py-10">
    <div class="min-w-0 w-full">
      <div class="max-w-7xl">
        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight">Хранилище гильдии</h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Предметы, выдачи и складские операции гильдии
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Tooltip v-if="model.dkpEnabled" content="Ваши ДКП в этой гильдии" side="bottom">
              <span
                class="inline-flex h-9 cursor-help items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-700"
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
                  <circle cx="12" cy="12" r="9" />
                  <path d="M12 7v5l3 2" />
                </svg>
                <span>ДКП: {{ model.myDkpBalance ?? 0 }}</span>
              </span>
            </Tooltip>
            <RouterLink
              v-if="model.dkpLedgerAvailable"
              :to="{ name: 'guild-bank-dkp-history', params: { id: model.guildId } }"
              class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-semibold text-primary transition-colors hover:border-primary/25 hover:bg-primary/5"
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
                <path d="M3 12a9 9 0 1 0 3-6.7" />
                <path d="M3 4v5h5" />
                <path d="M12 7v5l3 2" />
              </svg>
              <span>История ДКП</span>
            </RouterLink>
          </div>
        </div>

        <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
          <div
            v-for="stat in stats"
            :key="stat.label"
            class="flex items-center gap-4 rounded-lg border border-border bg-card px-4 py-3"
          >
            <div
              :class="[
                'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                stat.key === 'items' && 'bg-blue-50 text-blue-600',
                stat.key === 'stock' && 'bg-emerald-50 text-emerald-600',
                stat.key === 'grants' && 'bg-violet-50 text-violet-600',
              ]"
              aria-hidden="true"
            >
              <svg
                v-if="stat.key === 'items'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-5"
              >
                <path d="M6 3h12l2 4v14H4V7l2-4Z" />
                <path d="M4 7h16" />
                <path d="M9 12h6" />
                <path d="M9 16h6" />
              </svg>
              <svg
                v-else-if="stat.key === 'stock'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-5"
              >
                <path d="m12 3 8 4-8 4-8-4 8-4Z" />
                <path d="m4 12 8 4 8-4" />
                <path d="m4 17 8 4 8-4" />
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
              >
                <path d="M12 3v12" />
                <path d="m8 11 4 4 4-4" />
                <path d="M5 21h14" />
              </svg>
            </div>
            <div class="min-w-0">
              <div class="text-xs font-medium text-muted-foreground">{{ stat.label }}</div>
              <div class="mt-1 text-xl font-bold tabular-nums tracking-tight">
                {{ Number(stat.value).toLocaleString('ru-RU') }}
              </div>
            </div>
          </div>
        </div>

        <p v-if="model.loading" class="text-sm text-muted-foreground">Загрузка...</p>
        <p v-else-if="model.error" class="text-sm text-destructive">{{ model.error }}</p>

        <div v-else class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(390px,0.9fr)]">
          <GuildBankItemsCard
            v-model:items-search="model.itemsSearch"
            :items="model.items"
            :filtered-items="model.filteredItems"
            :selected-item-id="model.selectedItemId"
            :can-add-items="model.canAddItems"
            :can-delete-items="model.canDeleteItems"
            :dkp-enabled="model.dkpEnabled"
            @create="model.openCreateItem"
            @open-tiers="model.openTiersModal"
            @select="model.selectItem"
            @restock="model.openRestockModal"
            @edit="model.openEditItem"
            @delete="model.openDeleteItemDialog"
          />

          <GuildBankGrantsCard
            v-model:grants-search="model.grantsSearch"
            :selected-item="model.selectedItem"
            :grants="model.grants"
            :filtered-grants="model.filteredGrants"
            :grants-loading="model.grantsLoading"
            :grants-error="model.grantsError"
            :can-grant-items="model.canGrantItems"
            :revoking-grant-id="model.revokingGrantId"
            :dkp-enabled="model.dkpEnabled"
            @grant="model.openGrant"
            @revoke="model.openRevokeGrantDialog"
          />
        </div>
      </div>
    </div>

    <GuildBankTiersDialog
      v-model:open="model.tiersModalOpen"
      v-model:form="model.tierForm"
      v-model:delete-dialog-open="model.deleteTierDialogOpen"
      :tiers="model.tiers"
      :tiers-loading="model.tiersLoading"
      :tiers-error="model.tiersError"
      :form-error="model.tierFormError"
      :saving="model.tierFormSaving"
      :tier-editing="model.tierEditing"
      :tier-pending-delete="model.tierPendingDelete"
      :delete-dialog-error="model.deleteTierDialogError"
      :deleting-tier-id="model.deletingTierId"
      @save="model.saveTier"
      @edit="model.openEditTier"
      @cancel-edit="model.cancelEditTier"
      @delete="model.openDeleteTierDialog"
      @confirm-delete="model.confirmDeleteTier"
      @close-delete="model.closeDeleteTierDialog"
    />

    <GuildBankItemFormDialog
      v-model:open="model.itemFormOpen"
      v-model:form="model.itemForm"
      :item-editing="model.itemEditing"
      :tiers="model.tiers"
      :dkp-enabled="model.dkpEnabled"
      :form-error="model.itemFormError"
      :saving="model.itemFormSaving"
      @save="model.saveItem"
    />

    <GuildBankRestockDialog
      v-if="model.restockItem"
      v-model:open="model.restockModalOpen"
      v-model:amount="model.restockAmount"
      :restock-item="model.restockItem"
      :form-error="model.restockError"
      :saving="model.restockSaving"
      @cancel="model.closeRestockModal"
      @save="model.saveRestock"
    />

    <GuildBankGrantFormDialog
      v-model:open="model.grantFormOpen"
      v-model:form="model.grantForm"
      v-model:member-select-open="model.memberSelectOpen"
      v-model:member-search="model.memberSearch"
      :selected-item="model.selectedItem"
      :selected-roster-member="model.selectedRosterMember"
      :filtered-roster-members="model.filteredRosterMembers"
      :roster-loading="model.rosterLoading"
      :roster-error="model.rosterError"
      :form-error="model.grantFormError"
      :saving="model.grantFormSaving"
      @save="model.saveGrant"
    />

    <GuildBankDeleteItemDialog
      v-model:open="model.deleteItemDialogOpen"
      :item-pending-delete="model.itemPendingDelete"
      :dialog-error="model.deleteItemDialogError"
      :loading="model.deletingItemId != null"
      @confirm="model.confirmDeleteItem"
      @close="model.closeDeleteItemDialog"
    />

    <GuildBankRevokeGrantDialog
      v-model:open="model.revokeDialogOpen"
      :grant-pending-revoke="model.grantPendingRevoke"
      :selected-item="model.selectedItem"
      :dialog-error="model.revokeDialogError"
      :loading="model.revokingGrantId != null"
      @confirm="model.confirmRevokeGrant"
      @close="model.closeRevokeDialog"
    />

    <GuildBankGrantDkpConfirmDialog
      v-model:open="model.grantNegativeConfirmOpen"
      :info="model.grantNegativeConfirmInfo"
      :saving="model.grantFormSaving"
      @confirm="model.confirmGrantWithNegativeBalance"
      @close="model.closeGrantNegativeConfirm"
    />
  </div>
</template>
