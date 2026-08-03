<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'radix-vue';
import { Gift, X } from '@lucide/vue';
import {
  Button,
  Input,
  Label,
  SearchableSelect,
} from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import type {
  ConstantPartyMember,
  ConstantPartyStorageItem,
} from '@/shared/api/constantPartiesApi';

const props = defineProps<{
  open: boolean;
  saving: boolean;
  items: ConstantPartyStorageItem[];
  members: ConstantPartyMember[];
}>();

const emit = defineEmits<{
  (event: 'update:open', value: boolean): void;
  (event: 'save', payload: {
    itemId: number;
    characterId: number;
    quantity: number;
    reason: string | null;
  }): void;
}>();

const itemId = ref<number | null>(null);
const characterId = ref<number | null>(null);
const quantity = ref('1');
const reason = ref('');

const itemOptions = computed(() => props.items.map((item) => ({
  value: item.id,
  label: `${item.name} · остаток: ${item.quantity ?? 'без ограничения'}`,
  disabled: item.quantity === 0,
})));
const memberOptions = computed(() => props.members.map((member) => ({
  value: member.character_id,
  label: member.character?.name ?? 'Персонаж',
})));
const selectedItem = computed(() => props.items.find((item) => item.id === itemId.value) ?? null);
const quantityIsValid = computed(() => {
  const value = Number(quantity.value);
  if (!Number.isInteger(value) || value < 1 || value > 1_000_000_000) return false;
  return selectedItem.value?.quantity === null || value <= (selectedItem.value?.quantity ?? 0);
});
const canSave = computed(() => (
  itemId.value !== null
  && characterId.value !== null
  && quantityIsValid.value
  && !props.saving
));

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    itemId.value = null;
    characterId.value = null;
    quantity.value = '1';
    reason.value = '';
  },
);

function handleOpenChange(open: boolean) {
  if (!open && props.saving) return;
  emit('update:open', open);
}

function save() {
  if (!canSave.value || itemId.value === null || characterId.value === null) return;
  emit('save', {
    itemId: itemId.value,
    characterId: characterId.value,
    quantity: Number(quantity.value),
    reason: reason.value.trim() || null,
  });
}
</script>

<template>
  <DialogRoot
    :open="open"
    @update:open="handleOpenChange"
  >
    <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 shadow-lg data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
        >
          <div class="flex items-start gap-3 pr-10">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Gift class="size-5" />
            </span>
            <div>
              <DialogTitle class="text-lg font-semibold">
                Выдать предмет
              </DialogTitle>
              <DialogDescription class="sr-only">
                Выдача будет записана в историю участника.
              </DialogDescription>
            </div>
          </div>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            class="absolute right-4 top-4"
            :disabled="saving"
            title="Закрыть"
            aria-label="Закрыть"
            @click="handleOpenChange(false)"
          >
            <X class="size-4" />
          </Button>

          <form
            class="mt-6 space-y-4"
            @submit.prevent="save"
          >
            <div class="space-y-1.5">
              <Label>Предмет</Label>
              <SearchableSelect
                v-model="itemId"
                :options="itemOptions"
                placeholder="Выберите предмет"
                search-placeholder="Поиск предмета"
                empty-text="Предметы не найдены"
                aria-label="Предмет"
              />
            </div>

            <div class="space-y-1.5">
              <Label>Получатель</Label>
              <SearchableSelect
                v-model="characterId"
                :options="memberOptions"
                placeholder="Выберите персонажа"
                search-placeholder="Поиск персонажа"
                empty-text="Персонажи не найдены"
                aria-label="Получатель"
              />
            </div>

            <div class="space-y-1.5">
              <Label for="constant-party-storage-grant-quantity">
                Количество
              </Label>
              <Input
                id="constant-party-storage-grant-quantity"
                v-model="quantity"
                type="number"
                min="1"
                :max="selectedItem?.quantity ?? 1000000000"
                step="1"
                required
              />
              <p
                v-if="!quantityIsValid && itemId !== null"
                class="text-xs text-destructive"
              >
                Укажите доступное количество предметов.
              </p>
            </div>

            <div class="space-y-1.5">
              <Label for="constant-party-storage-grant-reason">
                Причина
                <span class="font-normal text-muted-foreground">(необязательно)</span>
              </Label>
              <Input
                id="constant-party-storage-grant-reason"
                v-model="reason"
                maxlength="1000"
                placeholder="Например, награда за рейд"
              />
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                :disabled="saving"
                @click="handleOpenChange(false)"
              >
                Отмена
              </Button>
              <Button
                type="submit"
                :disabled="!canSave"
              >
                {{ saving ? 'Выдача...' : 'Выдать' }}
              </Button>
            </div>
          </form>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
