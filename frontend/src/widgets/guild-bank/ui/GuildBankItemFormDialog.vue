<script setup lang="ts">
import { computed } from 'vue';
import { Button, Input, Label } from '@/shared/ui';
import { Select } from '@/shared/ui/select';
import type { GuildBankItemForm } from '@/features/guild-bank';
import type { GuildBankItem, GuildBankItemTier } from '@/shared/api/guildBankApi';

const props = defineProps<{
  itemEditing: GuildBankItem | null;
  tiers: GuildBankItemTier[];
  dkpEnabled: boolean;
  formError: string;
  saving: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const form = defineModel<GuildBankItemForm>('form', { required: true });

const emit = defineEmits<{
  save: [];
}>();

const NO_TIER_SELECT_VALUE = 'none';

const tierOptions = computed(() => [
  { value: NO_TIER_SELECT_VALUE, label: 'Без тира' },
  ...props.tiers.map((tier) => ({
    value: String(tier.id),
    label: tier.name,
  })),
]);

const tierSelectValue = computed({
  get: () => form.value.guild_bank_item_tier_id || NO_TIER_SELECT_VALUE,
  set: (value: string) => {
    form.value.guild_bank_item_tier_id = value === NO_TIER_SELECT_VALUE ? '' : value;
  },
});
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4">
    <div class="w-full max-w-2xl overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-xl">
      <div class="border-b border-border bg-muted/25 px-5 py-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-lg font-semibold">
              {{ itemEditing ? 'Редактирование предмета' : 'Новый предмет' }}
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
              Название, описание, тир, количество и стоимость ДКП
            </p>
          </div>
          <Button variant="ghost" size="icon" :disabled="saving" aria-label="Закрыть" @click="open = false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </Button>
        </div>
      </div>

      <div class="grid gap-4 px-5 py-5">
        <div class="space-y-2">
          <Label for="item-name">Название *</Label>
          <Input id="item-name" v-model="form.name" type="text" maxlength="255" required placeholder="Например: Эссенция пламени" />
        </div>

        <div class="space-y-2">
          <Label for="item-desc">Описание</Label>
          <textarea
            id="item-desc"
            v-model="form.description"
            rows="4"
            class="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
            placeholder="Кратко опишите предмет, назначение или правила выдачи"
          />
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div class="space-y-2">
            <Label for="item-tier">Тир</Label>
            <Select
              id="item-tier"
              v-model="tierSelectValue"
              :options="tierOptions"
              :disabled="saving"
              placeholder="Без тира"
              trigger-class="w-full"
            />
          </div>
          <div class="space-y-2">
            <Label for="item-qty">Количество</Label>
            <Input id="item-qty" v-model="form.quantity" type="number" min="0" placeholder="∞" />
          </div>
          <div class="space-y-2">
            <Label for="item-dkp">Стоимость ДКП</Label>
            <Input
              id="item-dkp"
              v-model="form.dkp_cost"
              type="number"
              min="0"
              step="1"
              :disabled="!dkpEnabled"
              :placeholder="dkpEnabled ? '0' : 'ДКП отключены'"
            />
          </div>
        </div>

        <div v-if="tiers.length" class="rounded-lg border border-border bg-muted/25 p-3">
          <div class="mb-2 text-xs font-medium text-muted-foreground">Доступные тиры</div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="tier in tiers"
              :key="tier.id"
              type="button"
              class="inline-flex h-7 items-center rounded-md px-2 text-xs font-semibold text-white"
              :style="{ backgroundColor: tier.color ?? '#64748b' }"
              @click="form.guild_bank_item_tier_id = String(tier.id)"
            >
              {{ tier.name }}
            </button>
          </div>
        </div>

        <p v-if="formError" class="rounded-md border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {{ formError }}
        </p>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-border bg-muted/20 px-5 py-4">
        <Button variant="outline" :disabled="saving" @click="open = false">Отмена</Button>
        <Button :disabled="saving" @click="emit('save')">
          {{ saving ? 'Сохранение...' : 'Сохранить' }}
        </Button>
      </div>
    </div>
  </div>
</template>
