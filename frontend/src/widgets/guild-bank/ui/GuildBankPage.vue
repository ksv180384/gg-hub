<script setup lang="ts">
import { reactive } from 'vue';
import { RouterLink } from 'vue-router';
import { useGuildBank } from '@/features/guild-bank';
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
</script>

<template>
  <div class="container py-6 md:py-8 overflow-x-hidden">
    <div class="min-w-0 w-full space-y-4">
      <div class="max-w-2xl">
        <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2">
          <h1 class="text-xl font-semibold">Хранилище гильдии</h1>
          <p
            v-if="model.dkpEnabled"
            class="text-sm text-muted-foreground"
            aria-label="Ваши очки ДКП"
          >
            ДКП:
            <span class="ml-1 font-semibold tabular-nums text-foreground">
              {{ model.myDkpBalance ?? 0 }}
            </span>
          </p>
          <RouterLink
            v-if="model.dkpLedgerAvailable"
            :to="{ name: 'guild-bank-dkp-history', params: { id: model.guildId } }"
            class="text-sm text-primary hover:underline"
          >
            История ДКП
          </RouterLink>
        </div>

        <p v-if="model.loading" class="text-sm text-muted-foreground">Загрузка…</p>
        <p v-else-if="model.error" class="text-sm text-destructive">{{ model.error }}</p>

        <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
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
      :tier-pending-delete="model.tierPendingDelete"
      :delete-dialog-error="model.deleteTierDialogError"
      :deleting-tier-id="model.deletingTierId"
      @create="model.createTier"
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
