<script setup lang="ts">
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';

defineProps<{
  open: boolean;
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
    title="Покинуть гильдию?"
    :description="'Вы перестанете быть участником этой гильдии. Доступ к настройкам и разделам гильдии будет закрыт.'"
    confirm-label="Покинуть"
    cancel-label="Отмена"
    :loading="loading"
    confirm-variant="destructive"
    @update:open="(v) => emit('update:open', v)"
    @confirm="emit('confirm')"
  />
</template>

