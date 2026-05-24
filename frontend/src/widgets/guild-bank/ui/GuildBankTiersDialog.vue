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
  tierEditing: GuildBankItemTier | null;
  tierPendingDelete: GuildBankItemTier | null;
  deleteDialogError: string;
  deletingTierId: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const form = defineModel<GuildBankTierForm>('form', { required: true });
const deleteDialogOpen = defineModel<boolean>('deleteDialogOpen', { required: true });

const emit = defineEmits<{
  save: [];
  edit: [tier: GuildBankItemTier];
  cancelEdit: [];
  delete: [tier: GuildBankItemTier];
  confirmDelete: [];
  closeDelete: [];
}>();

const sortedTiers = computed(() =>
  [...props.tiers].sort((a, b) => a.name.localeCompare(b.name, 'ru')),
);
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4">
    <div class="flex max-h-[min(90vh,44rem)] w-full max-w-2xl flex-col overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-xl">
      <div class="border-b border-border bg-muted/25 px-5 py-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-lg font-semibold">Тиры предметов</div>
            <p class="mt-1 text-sm text-muted-foreground">
              Цвет тира отображается на предмете в списке и карточке выдач.
            </p>
          </div>
          <Button variant="ghost" size="icon" aria-label="Закрыть" @click="open = false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </Button>
        </div>
      </div>

      <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
        <div class="rounded-lg border border-border bg-muted/20 p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <div class="text-sm font-semibold">
              {{ tierEditing ? 'Редактировать тир' : 'Добавить тир' }}
            </div>
            <Button
              v-if="tierEditing"
              type="button"
              variant="ghost"
              size="sm"
              :disabled="saving"
              @click="emit('cancelEdit')"
            >
              Отмена
            </Button>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto] md:items-end">
            <div class="space-y-2">
              <Label for="tier-name">Название *</Label>
              <Input
                id="tier-name"
                v-model="form.name"
                type="text"
                maxlength="50"
                class="h-9"
                :disabled="saving"
                placeholder="Например: S, A, Редкий"
                required
              />
            </div>
            <div class="space-y-2">
              <Label for="tier-color">Цвет *</Label>
              <ColorPicker id="tier-color" v-model="form.color" :disabled="saving" />
            </div>
            <Button class="h-9" :disabled="saving" @click="emit('save')">
              {{ saving ? 'Сохранение...' : tierEditing ? 'Сохранить' : 'Добавить' }}
            </Button>
          </div>
          <p v-if="formError" class="mt-3 rounded-md border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-destructive">
            {{ formError }}
          </p>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold">Список тиров</p>
            <p class="text-xs text-muted-foreground">{{ sortedTiers.length }} всего</p>
          </div>
          <p v-if="tiersLoading" class="text-sm text-muted-foreground">Загрузка...</p>
          <p v-else-if="tiersError" class="text-sm text-destructive">{{ tiersError }}</p>
          <p v-else-if="!sortedTiers.length" class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
            Пока нет тиров. Добавьте первый цветовой ярлык.
          </p>
          <ul v-else class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <li
              v-for="tier in sortedTiers"
              :key="tier.id"
              class="flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-3"
            >
              <div class="min-w-0 flex items-center gap-3">
                <span
                  class="inline-block h-8 w-1.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: tier.color ?? '#64748b' }"
                  aria-hidden="true"
                />
                <div class="min-w-0">
                  <div class="truncate text-sm font-semibold">{{ tier.name }}</div>
                  <div class="text-xs text-muted-foreground">
                    Предметов: {{ tier.items_count ?? 0 }}
                  </div>
                </div>
              </div>
              <div class="flex shrink-0 items-center gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8"
                  :disabled="saving || deletingTierId === tier.id"
                  title="Редактировать тир"
                  aria-label="Редактировать тир"
                  @click="emit('edit', tier)"
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
                    <path d="M12 20h9" />
                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" />
                  </svg>
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 text-destructive hover:text-destructive"
                  :disabled="(tier.items_count ?? 0) > 0 || deletingTierId === tier.id"
                  :title="
                    (tier.items_count ?? 0) > 0
                      ? 'Нельзя удалить: тир назначен предметам.'
                      : 'Удалить тир'
                  "
                  :aria-label="
                    (tier.items_count ?? 0) > 0
                      ? 'Нельзя удалить: тир назначен предметам.'
                      : 'Удалить тир'
                  "
                  @click="emit('delete', tier)"
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
                    <path d="M3 6h18" />
                    <path d="M8 6V4h8v2" />
                    <path d="M19 6l-1 14H6L5 6" />
                    <path d="M10 11v5" />
                    <path d="M14 11v5" />
                  </svg>
                </Button>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4">
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
