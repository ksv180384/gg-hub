<script setup lang="ts">
import {
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { Button, Input, Label, SelectRoot, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/shared/ui';
import type { ApplicationFormFieldType } from '@/features/guild-settings';

const props = defineProps<{
  open: boolean;
  saving: boolean;
  editIndex: number | null;
  name: string;
  type: ApplicationFormFieldType;
  required: boolean;
  options: string[];
  typeOptions: { value: ApplicationFormFieldType; label: string }[];
  isSelectOrMultiselect: (t: ApplicationFormFieldType) => boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'update:name', value: string): void;
  (e: 'update:type', value: ApplicationFormFieldType): void;
  (e: 'update:required', value: boolean): void;
  (e: 'add-option'): void;
  (e: 'remove-option', index: number): void;
  (e: 'set-option', payload: { index: number; value: string }): void;
  (e: 'save'): void;
  (e: 'cancel'): void;
}>();

function canSave(): boolean {
  if (!props.name.trim()) return false;
  if (!props.isSelectOrMultiselect(props.type)) return true;
  return props.options.some((o) => o.trim());
}
</script>

<template>
  <DialogRoot
    :open="open"
    @update:open="(v: boolean) => emit('update:open', v)"
  >
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
            {{ editIndex !== null ? 'Редактировать поле' : 'Добавить дополнительное поле' }}
          </DialogTitle>
          <DialogDescription class="sr-only">
            Название поля, тип поля и обязательность заполнения.
          </DialogDescription>

          <div class="space-y-4 pt-2">
            <div class="space-y-2">
              <Label for="application-field-name">Название поля *</Label>
              <Input
                id="application-field-name"
                :model-value="name"
                placeholder="Например: О себе"
                :disabled="saving"
                @update:model-value="emit('update:name', $event)"
              />
            </div>

            <div class="space-y-2">
              <Label for="application-field-type">Тип поля *</Label>
              <SelectRoot
                :model-value="type"
                :disabled="saving"
                @update:model-value="emit('update:type', $event as ApplicationFormFieldType)"
              >
                <SelectTrigger id="application-field-type" class="w-full">
                  <SelectValue placeholder="Выберите тип" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
            </div>

            <div class="flex items-center gap-2">
              <input
                id="application-field-required"
                :checked="required"
                type="checkbox"
                class="h-4 w-4 rounded border-input"
                @change="emit('update:required', ($event.target as HTMLInputElement).checked)"
              />
              <Label for="application-field-required" class="cursor-pointer font-normal">
                Обязательное для заполнения
              </Label>
            </div>

            <template v-if="isSelectOrMultiselect(type)">
              <div class="space-y-2">
                <Label>Варианты выбора *</Label>
                <p class="text-xs text-muted-foreground">
                  Добавьте позиции, которые можно будет выбрать при подаче заявки.
                </p>
                <div class="space-y-2">
                  <div
                    v-for="(opt, optIndex) in options"
                    :key="optIndex"
                    class="flex items-center gap-2"
                  >
                    <Input
                      :model-value="opt"
                      placeholder="Текст варианта"
                      class="flex-1"
                      :disabled="saving"
                      @update:model-value="emit('set-option', { index: optIndex, value: $event })"
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="h-9 w-9 shrink-0 text-destructive hover:text-destructive"
                      aria-label="Удалить вариант"
                      :disabled="saving"
                      @click="emit('remove-option', optIndex)"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      >
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                      </svg>
                    </Button>
                  </div>
                </div>

                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  :disabled="saving"
                  @click="emit('add-option')"
                >
                  + Добавить вариант
                </Button>
              </div>
            </template>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <Button type="button" variant="outline" :disabled="saving" @click="emit('cancel')">
              Отмена
            </Button>
            <Button type="button" :disabled="saving || !canSave()" @click="emit('save')">
              {{ saving ? 'Сохранение…' : editIndex !== null ? 'Сохранить' : 'Добавить' }}
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>

