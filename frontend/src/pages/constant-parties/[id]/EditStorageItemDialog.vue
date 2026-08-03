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
import { X } from '@lucide/vue';
import { Button, Label } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import type { ConstantPartyStorageItem } from '@/shared/api/constantPartiesApi';

const props = defineProps<{
  open: boolean;
  item: ConstantPartyStorageItem | null;
  saving: boolean;
}>();

const emit = defineEmits<{
  (event: 'update:open', value: boolean): void;
  (event: 'save', payload: { name: string; quantity: number | null }): void;
}>();

const name = ref('');
const quantity = ref<number | ''>(0);
const unlimited = ref(false);

const quantityIsValid = computed(() => (
  unlimited.value
  || (
    quantity.value !== ''
    && Number.isInteger(Number(quantity.value))
    && Number(quantity.value) >= 0
    && Number(quantity.value) <= 1_000_000_000
  )
));
const canSave = computed(() => (
  name.value.trim().length > 0
  && quantityIsValid.value
  && !props.saving
));

watch(
  () => [props.open, props.item] as const,
  ([open, item]) => {
    if (!open || !item) return;
    name.value = item.name;
    unlimited.value = item.quantity === null;
    quantity.value = item.quantity ?? 0;
  },
  { immediate: true },
);

function save() {
  if (!canSave.value) return;
  emit('save', {
    name: name.value.trim(),
    quantity: unlimited.value ? null : Number(quantity.value),
  });
}

function handleOpenChange(open: boolean) {
  if (!open && props.saving) return;
  emit('update:open', open);
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
          <div class="pr-10">
            <DialogTitle class="text-lg font-semibold">
              Редактировать предмет
            </DialogTitle>
            <DialogDescription class="sr-only">
              Изменение названия предмета и остатка в хранилище КП
            </DialogDescription>
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
            class="mt-5 space-y-4"
            @submit.prevent="save"
          >
            <div class="space-y-1.5">
              <Label for="constant-party-storage-item-name">
                Название
              </Label>
              <input
                id="constant-party-storage-item-name"
                v-model="name"
                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                maxlength="255"
                required
              />
            </div>

            <div class="space-y-1.5">
              <Label for="constant-party-storage-item-quantity">
                Остаток
              </Label>
              <input
                id="constant-party-storage-item-quantity"
                v-model.number="quantity"
                type="number"
                min="0"
                max="1000000000"
                step="1"
                class="h-9 w-full rounded-md border bg-background px-3 text-sm disabled:bg-muted/40 disabled:text-muted-foreground"
                :disabled="unlimited"
                required
              />
              <p
                v-if="!quantityIsValid"
                class="text-xs text-destructive"
              >
                Укажите целое число от 0 до 1 000 000 000.
              </p>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm">
              <input
                v-model="unlimited"
                type="checkbox"
                class="size-4 rounded border-border accent-primary"
              />
              Без ограничения
            </label>

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
                {{ saving ? 'Сохранение...' : 'Сохранить' }}
              </Button>
            </div>
          </form>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
