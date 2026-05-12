<script setup lang="ts">
import { Button } from '@/shared/ui';
import type { GuildBankGrantDkpConfirmation } from '@/shared/api/guildBankApi';

defineProps<{
  info: GuildBankGrantDkpConfirmation | null;
  saving: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
  confirm: [];
  close: [];
}>();
</script>

<template>
  <div v-if="open && info" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-xl border border-border bg-card text-card-foreground shadow-lg">
      <div class="border-b border-border px-4 py-3">
        <div class="text-base font-semibold">Недостаточно ДКП</div>
      </div>
      <div class="space-y-3 px-4 py-4 text-sm">
        <p>
          У участника {{ info.balance }} ДКП, для выдачи нужно {{ info.charged }}.
          После выдачи баланс станет
          <span class="font-medium text-destructive">{{ info.balance_after }}</span>.
        </p>
        <p class="text-muted-foreground">Продолжить выдачу с отрицательным балансом?</p>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
        <Button variant="outline" :disabled="saving" @click="emit('close')">Отмена</Button>
        <Button :disabled="saving" @click="emit('confirm')">
          {{ saving ? 'Выдача…' : 'Выдать' }}
        </Button>
      </div>
    </div>
  </div>
</template>
