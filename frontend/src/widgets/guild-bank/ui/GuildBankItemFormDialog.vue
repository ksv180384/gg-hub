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
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-lg rounded-xl border border-border bg-card text-card-foreground shadow-lg">
      <div class="border-b border-border px-4 py-3">
        <div class="text-base font-semibold">
          {{ itemEditing ? 'Редактирование предмета' : 'Новый предмет' }}
        </div>
      </div>
      <div class="space-y-4 px-4 py-4">
        <div class="space-y-2">
          <Label for="item-name">Название *</Label>
          <Input id="item-name" v-model="form.name" type="text" maxlength="255" required />
        </div>
        <div class="space-y-2">
          <Label for="item-desc">Описание</Label>
          <textarea
            id="item-desc"
            v-model="form.description"
            rows="3"
            class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            placeholder="Описание (необязательно)"
          />
        </div>
        <div
          class="grid grid-cols-1 gap-3"
          :class="dkpEnabled ? 'sm:grid-cols-2' : 'sm:grid-cols-2'"
        >
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
          <div v-if="dkpEnabled" class="space-y-2 sm:col-span-2">
            <Label for="item-dkp">Стоимость ДКП</Label>
            <Input id="item-dkp" v-model="form.dkp_cost" type="number" min="0" step="1" />
          </div>
        </div>

        <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
        <Button variant="outline" :disabled="saving" @click="open = false">Отмена</Button>
        <Button :disabled="saving" @click="emit('save')">{{ saving ? 'Сохранение…' : 'Сохранить' }}</Button>
      </div>
    </div>
  </div>
</template>
