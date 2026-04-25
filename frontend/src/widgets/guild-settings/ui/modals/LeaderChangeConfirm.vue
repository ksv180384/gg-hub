<script setup lang="ts">
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';

defineProps<{
  open: boolean;
  newLeaderName: string;
  loading: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'confirm'): void;
}>();
</script>

<template>
  <ConfirmDialog
    :open="open"
    title="Сменить лидера гильдии?"
    :loading="loading"
    confirm-label="Сменить лидера"
    cancel-label="Отмена"
    confirm-variant="destructive"
    @update:open="(v) => emit('update:open', v)"
    @confirm="emit('confirm')"
  >
    <template #description>
      {{
        newLeaderName
          ? `Лидером гильдии станет «${newLeaderName}». Вы получите роль «Новичок» и потеряете доступ к настройкам гильдии. Действие нельзя отменить — новому лидеру придётся передать роль обратно.`
          : 'Вы получите роль «Новичок» и потеряете доступ к настройкам гильдии. Действие нельзя отменить — новому лидеру придётся передать роль обратно.'
      }}
    </template>
  </ConfirmDialog>
</template>

