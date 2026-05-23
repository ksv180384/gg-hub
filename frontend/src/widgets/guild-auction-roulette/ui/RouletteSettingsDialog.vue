<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import { Button } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import type { GuildAuctionRouletteModel } from '@/features/guild-auction-roulette';
import WheelDurationField from './WheelDurationField.vue';

const props = defineProps<{
  open: boolean;
  model: GuildAuctionRouletteModel;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
}>();

function onOpenChange(value: boolean) {
  emit('update:open', value);
}
</script>

<template>
  <DialogRoot :open="open" @update:open="onOpenChange">
    <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            Настройки рулетки
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Эти настройки влияют на общий розыгрыш для всех участников комнаты.
          </DialogDescription>

          <div class="pt-2">
            <WheelDurationField
              v-model="props.model.wheelSpinDurationSeconds"
              :countdown-seconds="props.model.wheelSpinCountdownSeconds"
              :disabled="props.model.isWheelSpinning"
              @blur="props.model.clampWheelSpinSecondsField"
            />
          </div>

          <label
            class="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/20 p-3 text-sm transition-colors hover:bg-muted/35"
            :class="{ 'cursor-not-allowed opacity-60': props.model.isWheelSpinning }"
          >
            <input
              v-model="props.model.eliminationMode"
              type="checkbox"
              class="mt-0.5 size-4 rounded border-border accent-primary"
              :disabled="props.model.isWheelSpinning"
            />
            <span class="grid gap-1">
              <span class="font-medium text-foreground">На убывание</span>
              <span class="text-muted-foreground">
                Выпавший персонаж исключается из колеса. Розыгрыш продолжается,
                пока не останется один победитель.
              </span>
            </span>
          </label>

          <label
            class="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/20 p-3 text-sm transition-colors hover:bg-muted/35"
            :class="{ 'cursor-not-allowed opacity-60': props.model.isWheelSpinning }"
          >
            <input
              v-model="props.model.useDkpCoefficients"
              type="checkbox"
              class="mt-0.5 size-4 rounded border-border accent-primary"
              :disabled="props.model.isWheelSpinning"
            />
            <span class="grid gap-1">
              <span class="font-medium text-foreground">Учитывать коэффициенты</span>
              <span class="text-muted-foreground">
                Размер сектора персонажа зависит от его коэффициента. У участников не из гильдии вес равен 1.
              </span>
            </span>
          </label>

          <div class="flex justify-end gap-2 pt-2">
            <Button variant="outline" @click="onOpenChange(false)">
              Закрыть
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>

