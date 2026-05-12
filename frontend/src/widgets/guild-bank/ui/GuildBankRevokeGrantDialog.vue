<script setup lang="ts">
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import type { GuildBankGrant, GuildBankItem } from '@/shared/api/guildBankApi';

defineProps<{
  grantPendingRevoke: GuildBankGrant | null;
  selectedItem: GuildBankItem | null;
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
    title="Отменить выдачу"
    confirm-label="Отменить выдачу"
    confirm-variant="destructive"
    :loading="loading"
    @confirm="emit('confirm')"
    @update:open="(value) => { if (!value) emit('close'); }"
  >
    <template #description>
      <template v-if="grantPendingRevoke && selectedItem">
        <p>
          Отменить выдачу предмета
          <span class="font-medium text-foreground">«{{ selectedItem.name }}»</span>
          персонажу
          <span class="font-medium text-foreground">{{
            grantPendingRevoke.received_by_character?.name
              ?? `Персонаж #${grantPendingRevoke.received_by_character_id}`
          }}</span>?
        </p>
        <p class="mt-2">
          Если для предмета задано количество на складе, одна единица вернётся в хранилище.
        </p>
      </template>
      <p v-else>Отменить эту выдачу?</p>
      <p v-if="dialogError" class="mt-2 text-sm text-destructive">{{ dialogError }}</p>
    </template>
  </ConfirmDialog>
</template>
