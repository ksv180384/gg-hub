<script setup lang="ts">
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import type { GuildBankItem } from '@/shared/api/guildBankApi';

defineProps<{
  itemPendingDelete: GuildBankItem | null;
  dialogError: string;
  loading: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
  confirm: [];
  close: [];
}>();
</script>

<template>
  <ConfirmDialog
    v-model:open="open"
    title="Удалить предмет"
    confirm-label="Удалить"
    confirm-variant="destructive"
    :loading="loading"
    @confirm="emit('confirm')"
    @update:open="(value) => { if (!value) emit('close'); }"
  >
    <template #description>
      <template v-if="itemPendingDelete">
        <p>
          Удалить предмет
          <span class="font-medium text-foreground">«{{ itemPendingDelete.name }}»</span>
          из хранилища? Это действие нельзя отменить.
        </p>
      </template>
      <p v-else>Удалить этот предмет?</p>
      <p v-if="dialogError" class="mt-2 text-sm text-destructive">{{ dialogError }}</p>
    </template>
  </ConfirmDialog>
</template>
