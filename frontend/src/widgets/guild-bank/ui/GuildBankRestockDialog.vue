<script setup lang="ts">
import { Button, Input, Label } from '@/shared/ui';
import type { GuildBankItem } from '@/shared/api/guildBankApi';

defineProps<{
  restockItem: GuildBankItem;
  formError: string;
  saving: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const amount = defineModel<string>('amount', { required: true });

const emit = defineEmits<{
  cancel: [];
  save: [];
}>();
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-lg rounded-xl border border-border bg-card text-card-foreground shadow-lg">
      <div class="border-b border-border px-4 py-3">
        <div class="text-base font-semibold">Добавить предметы на склад</div>
        <div class="text-xs text-muted-foreground mt-0.5 truncate">{{ restockItem.name }}</div>
      </div>
      <div class="space-y-4 px-4 py-4">
        <p v-if="restockItem.quantity != null" class="text-sm text-muted-foreground">
          Сейчас на складе:
          <span class="font-medium text-foreground">{{ restockItem.quantity }}</span>.
          Укажите, сколько единиц добавить.
        </p>
        <p v-else class="text-sm text-muted-foreground">
          Остаток сейчас не ограничен (∞). Укажите, сколько единиц добавить — для предмета будет включён учёт количества на складе.
        </p>
        <div class="space-y-2">
          <Label for="restock-amount">Количество *</Label>
          <Input
            id="restock-amount"
            v-model="amount"
            type="number"
            min="1"
            step="1"
            class="h-9"
            placeholder="Например 10"
          />
        </div>
        <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
        <Button variant="outline" :disabled="saving" @click="emit('cancel')">Отмена</Button>
        <Button :disabled="saving" @click="emit('save')">
          {{ saving ? 'Сохранение…' : 'Добавить' }}
        </Button>
      </div>
    </div>
  </div>
</template>
