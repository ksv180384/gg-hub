<script setup lang="ts">
import { computed } from 'vue';
import { Button, ColorPicker, Input, Label } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import type { GuildBankTierForm } from '@/features/guild-bank';
import type { GuildBankItemTier } from '@/shared/api/guildBankApi';

const props = defineProps<{
  tiers: GuildBankItemTier[];
  tiersLoading: boolean;
  tiersError: string;
  formError: string;
  saving: boolean;
  tierPendingDelete: GuildBankItemTier | null;
  deleteDialogError: string;
  deletingTierId: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const form = defineModel<GuildBankTierForm>('form', { required: true });
const deleteDialogOpen = defineModel<boolean>('deleteDialogOpen', { required: true });

const emit = defineEmits<{
  create: [];
  delete: [tier: GuildBankItemTier];
  confirmDelete: [];
  closeDelete: [];
}>();

const sortedTiers = computed(() =>
  [...props.tiers].sort((a, b) => a.name.localeCompare(b.name, 'ru')),
);
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="flex max-h-[min(90vh,40rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-lg">
      <div class="border-b border-border px-4 py-3">
        <div class="text-base font-semibold">Тиры предметов</div>
        <p class="mt-1 text-xs text-muted-foreground">
          Тиры привязаны к гильдии. Удалить можно только тир без предметов.
        </p>
      </div>

      <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4">
        <div class="space-y-3 rounded-lg border border-border p-3">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:items-end">
            <div class="space-y-2">
              <Label for="tier-name">Название *</Label>
              <Input
                id="tier-name"
                v-model="form.name"
                type="text"
                maxlength="50"
                class="h-9"
                :disabled="saving"
                required
              />
            </div>
            <div class="space-y-2">
              <Label for="tier-color">Цвет *</Label>
              <ColorPicker id="tier-color" v-model="form.color" :disabled="saving" />
            </div>
          </div>
          <div class="flex justify-end">
            <Button :disabled="saving" @click="emit('create')">
              {{ saving ? 'Добавление…' : 'Добавить тир' }}
            </Button>
          </div>
          <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>
        </div>

        <div class="space-y-2">
          <p class="text-sm font-medium">Список тиров</p>
          <p v-if="tiersLoading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="tiersError" class="text-sm text-destructive">{{ tiersError }}</p>
          <p v-else-if="!sortedTiers.length" class="text-sm text-muted-foreground">Пока нет тиров.</p>
          <ul v-else class="space-y-2">
            <li
              v-for="tier in sortedTiers"
              :key="tier.id"
              class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
            >
              <div class="min-w-0 flex items-center gap-2">
                <span
                  class="inline-block h-3 w-3 shrink-0 rounded-full border"
                  :style="{ backgroundColor: tier.color ?? '#ffffff' }"
                  aria-hidden="true"
                />
                <div class="min-w-0">
                  <div class="truncate text-sm font-medium">{{ tier.name }}</div>
                  <div class="text-xs text-muted-foreground">
                    Предметов: {{ tier.items_count ?? 0 }}
                  </div>
                </div>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                class="shrink-0 text-destructive hover:text-destructive"
                :disabled="(tier.items_count ?? 0) > 0 || deletingTierId === tier.id"
                :title="
                  (tier.items_count ?? 0) > 0
                    ? 'Нельзя удалить: тир привязан к предметам.'
                    : 'Удалить тир'
                "
                @click="emit('delete', tier)"
              >
                Удалить
              </Button>
            </li>
          </ul>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
        <Button variant="outline" @click="open = false">Закрыть</Button>
      </div>
    </div>
  </div>

  <ConfirmDialog
    v-model:open="deleteDialogOpen"
    title="Удалить тир"
    confirm-label="Удалить"
    confirm-variant="destructive"
    :loading="deletingTierId != null"
    @confirm="emit('confirmDelete')"
    @update:open="(value) => { if (!value) emit('closeDelete'); }"
  >
    <template #description>
      <template v-if="tierPendingDelete">
        <p>
          Удалить тир
          <span class="font-medium text-foreground">«{{ tierPendingDelete.name }}»</span>?
        </p>
      </template>
      <p v-else>Удалить этот тир?</p>
      <p v-if="deleteDialogError" class="mt-2 text-sm text-destructive">{{ deleteDialogError }}</p>
    </template>
  </ConfirmDialog>
</template>
