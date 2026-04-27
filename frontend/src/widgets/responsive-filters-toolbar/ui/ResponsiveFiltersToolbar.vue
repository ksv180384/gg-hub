<script setup lang="ts">
import { ref } from 'vue';
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
} from 'radix-vue';
import { Card, CardContent, Input, Button, Label } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { cn } from '@/shared/lib/utils';

const name = defineModel<string>('name', { default: '' });

const emit = defineEmits<{
  reset: [];
}>();

withDefaults(
  defineProps<{
    /** Подсветка кнопки «ещё фильтры» на мобильной раскладке */
    extraFiltersActive?: boolean;
    nameLabel?: string;
    namePlaceholder?: string;
    /** Заголовок панели в попапе на мобильной */
    extraFiltersTitle?: string;
    popoverTriggerTitle?: string;
    popoverTriggerAriaLabel?: string;
    resetButtonTitle?: string;
    resetButtonAriaLabel?: string;
    nameMobileInputId?: string;
    nameDesktopInputId?: string;
    cardClass?: string;
    cardContentClass?: string;
  }>(),
  {
    extraFiltersActive: false,
    nameLabel: 'Имя',
    namePlaceholder: '',
    extraFiltersTitle: 'Дополнительные фильтры',
    popoverTriggerTitle: 'Дополнительные фильтры',
    popoverTriggerAriaLabel: 'Открыть дополнительные фильтры',
    resetButtonTitle: 'Сбросить фильтры',
    resetButtonAriaLabel: 'Сбросить фильтры',
    nameMobileInputId: 'responsive-filter-name-mobile',
    nameDesktopInputId: 'responsive-filter-name-desktop',
    cardClass: '',
    cardContentClass: 'p-4',
  },
);

const moreFiltersOpen = ref(false);
</script>

<template>
  <Card :class="cardClass">
    <CardContent :class="cardContentClass">
      <div class="flex flex-col gap-3">
        <!-- Мобильная панель: имя + дропдаун фильтров + кнопки -->
        <div class="flex w-full min-w-0 items-end gap-2 md:hidden">
          <div class="grid min-w-0 flex-1 gap-1.5">
            <Label :for="nameMobileInputId">{{ nameLabel }}</Label>
            <Input
              :id="nameMobileInputId"
              v-model="name"
              type="text"
              :placeholder="namePlaceholder || undefined"
              class="h-8"
            />
          </div>
          <PopoverRoot v-model:open="moreFiltersOpen">
            <PopoverTrigger as-child>
              <Button
                type="button"
                variant="secondary"
                :class="cn(
                  'relative h-8 w-8 shrink-0 cursor-pointer px-0',
                  extraFiltersActive && 'ring-1 ring-primary/60',
                )"
                :title="popoverTriggerTitle"
                :aria-label="popoverTriggerAriaLabel"
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
                  aria-hidden="true"
                >
                  <line x1="4" x2="4" y1="21" y2="14" />
                  <line x1="4" x2="4" y1="10" y2="3" />
                  <line x1="12" x2="12" y1="21" y2="12" />
                  <line x1="12" x2="12" y1="8" y2="3" />
                  <line x1="20" x2="20" y1="21" y2="16" />
                  <line x1="20" x2="20" y1="12" y2="3" />
                  <line x1="2" x2="6" y1="14" y2="14" />
                  <line x1="10" x2="14" y1="8" y2="8" />
                  <line x1="18" x2="22" y1="16" y2="16" />
                </svg>
              </Button>
            </PopoverTrigger>
            <ClientOnly>
              <PopoverPortal>
                <PopoverContent
                  side="bottom"
                  align="end"
                  :side-offset="8"
                  :class="cn(
                    'z-50 w-[min(calc(100vw-2rem),22rem)] max-h-[min(85vh,32rem)] overflow-y-auto rounded-md border bg-popover p-3 text-popover-foreground shadow-md',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                    'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    'data-[side=bottom]:slide-in-from-top-2',
                  )"
                >
                  <p class="mb-3 text-sm font-medium">{{ extraFiltersTitle }}</p>
                  <div class="flex flex-col gap-3">
                    <slot name="extra-filters" />
                  </div>
                </PopoverContent>
              </PopoverPortal>
            </ClientOnly>
          </PopoverRoot>
          <div class="flex shrink-0 gap-2">
            <Button
              type="button"
              variant="secondary"
              class="h-8 w-8 cursor-pointer px-0"
              :title="resetButtonTitle"
              :aria-label="resetButtonAriaLabel"
              @click="emit('reset')"
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
                aria-hidden="true"
              >
                <path d="M18 6 6 18" />
                <path d="M6 6 18 18" />
              </svg>
            </Button>
            <slot name="after-reset-actions" />
          </div>
        </div>

        <!-- Десктоп: имя + слот + кнопки -->
        <div
          class="hidden w-full min-w-0 flex-wrap items-end gap-2 md:flex md:flex-nowrap md:overflow-x-auto"
        >
          <div class="grid w-[7.5rem] shrink-0 gap-1.5 sm:w-36">
            <Label :for="nameDesktopInputId">{{ nameLabel }}</Label>
            <Input
              :id="nameDesktopInputId"
              v-model="name"
              type="text"
              :placeholder="namePlaceholder || undefined"
              class="h-8"
            />
          </div>
          <slot name="desktop-filters" />
          <div class="flex shrink-0 gap-2">
            <Button
              type="button"
              variant="secondary"
              class="h-8 w-8 cursor-pointer px-0"
              :title="resetButtonTitle"
              :aria-label="resetButtonAriaLabel"
              @click="emit('reset')"
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
                aria-hidden="true"
              >
                <path d="M18 6 6 18" />
                <path d="M6 6 18 18" />
              </svg>
            </Button>
            <slot name="after-reset-actions-desktop" />
          </div>
        </div>

        <slot name="footer" />
      </div>
    </CardContent>
  </Card>
</template>
