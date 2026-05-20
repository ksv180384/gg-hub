<script setup lang="ts">
import {
  Label,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';

defineProps<{
  open: boolean;
  loading: boolean;
  characters: { id: number; name: string; avatar_url?: string | null; is_leader?: boolean }[];
  selectedCharacterId: string;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'update:selectedCharacterId', value: string): void;
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
  >
    <template #description>
      <div class="space-y-4">
        <p>
          Выберите персонажа, который покинет гильдию. Если персонаж является
          лидером, сначала передайте лидерство другому участнику.
        </p>

        <div class="space-y-1.5 text-left">
          <Label>Персонаж</Label>
          <SelectRoot
            :model-value="selectedCharacterId"
            :disabled="loading || characters.length === 0"
            @update:model-value="emit('update:selectedCharacterId', String($event))"
          >
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Выберите персонажа" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="character in characters"
                :key="character.id"
                :value="String(character.id)"
              >
                {{ character.name }}
              </SelectItem>
            </SelectContent>
          </SelectRoot>
          <p v-if="characters.length === 0" class="text-xs text-destructive">
            Нет персонажей, которые могут покинуть гильдию.
          </p>
        </div>
      </div>
    </template>
  </ConfirmDialog>
</template>

