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
    title="Удалить гильдию?"
    confirm-label="Удалить"
    cancel-label="Отмена"
    :loading="loading"
    confirm-variant="destructive"
    @update:open="(v) => emit('update:open', v)"
    @confirm="emit('confirm')"
  >
    <template #description>
      <div class="space-y-2">
        <p>Все данные гильдии будут утеряны.</p>
        <p>Персонаж лидера станет без гильдии.</p>
      </div>
    </template>
  </ConfirmDialog>
</template>
