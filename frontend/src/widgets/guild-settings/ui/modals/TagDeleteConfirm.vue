<script setup lang="ts">
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import type { Tag } from '@/shared/api/tagsApi';

defineProps<{
  open: boolean;
  tag: Tag | null;
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
    :title="tag ? `Удалить тег «${tag.name}»?` : 'Удалить тег?'"
    description="Тег исчезнет из всех персонажей и гильдий. Это действие нельзя отменить."
    confirm-label="Удалить"
    cancel-label="Отмена"
    :loading="loading"
    confirm-variant="destructive"
    @update:open="(v) => emit('update:open', v)"
    @confirm="emit('confirm')"
  />
</template>

