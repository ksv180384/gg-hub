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
import { PackagePlus, X } from '@lucide/vue';
import { Button, Input, Label } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';

const props = defineProps<{
  open: boolean;
  saving: boolean;
}>();

const emit = defineEmits<{
  (event: 'update:open', value: boolean): void;
  (event: 'save', payload: { name: string; quantity: number | null }): void;
}>();

const name = ref('');
const quantity = ref('1');
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
  () => props.open,
  (open) => {
    if (!open) return;
    name.value = '';
    quantity.value = '1';
    unlimited.value = false;
  },
);

function handleOpenChange(open: boolean) {
  if (!open && props.saving) return;
  emit('update:open', open);
}

function save() {
  if (!canSave.value) return;
  emit('save', {
    name: name.value.trim(),
    quantity: unlimited.value ? null : Number(quantity.value),
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
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
              <PackagePlus class="size-5" />
            </span>
            <div>
              <DialogTitle class="text-lg font-semibold">
                Добавить предмет
              </DialogTitle>
              <DialogDescription class="sr-only">
                Новый предмет появится в хранилище конст-пати.
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
              <Label for="constant-party-new-storage-item-name">
                Название
              </Label>
              <Input
                id="constant-party-new-storage-item-name"
                v-model="name"
                maxlength="255"
                placeholder="Название предмета"
                required
              />
            </div>

            <div class="space-y-1.5">
              <Label for="constant-party-new-storage-item-quantity">
                Начальный остаток
              </Label>
              <Input
                id="constant-party-new-storage-item-quantity"
                v-model="quantity"
                type="number"
                min="0"
                max="1000000000"
                step="1"
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
                class="bg-emerald-700 text-white hover:bg-emerald-800 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                :disabled="!canSave"
              >
                {{ saving ? 'Добавление...' : 'Добавить' }}
              </Button>
            </div>
          </form>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
