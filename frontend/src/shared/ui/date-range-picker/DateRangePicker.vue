<script setup lang="ts">
import { computed, ref } from 'vue';
import type { DateRange } from 'radix-vue';
import {
  PopoverContent,
  PopoverPortal,
  PopoverRoot,
  PopoverTrigger,
  RangeCalendarCell,
  RangeCalendarCellTrigger,
  RangeCalendarGrid,
  RangeCalendarGridBody,
  RangeCalendarGridHead,
  RangeCalendarGridRow,
  RangeCalendarHeadCell,
  RangeCalendarHeader,
  RangeCalendarHeading,
  RangeCalendarNext,
  RangeCalendarPrev,
  RangeCalendarRoot,
} from 'radix-vue';

const model = defineModel<DateRange>({ required: true });
const open = ref(false);

const rangeLabel = computed(() => {
  if (!model.value.start && !model.value.end) {
    return 'Выберите период';
  }

  const start = formatDateValue(model.value.start);
  const end = formatDateValue(model.value.end);

  return `${start || '...'} – ${end || '...'}`;
});

function formatDateValue(value: DateRange['start']) {
  if (!value) return '';

  const [year, month, day] = value.toString().slice(0, 10).split('-');
  return `${day}.${month}.${year}`;
}

function clearRange() {
  model.value = {
    start: undefined,
    end: undefined,
  };
}
</script>

<template>
  <PopoverRoot v-model:open="open">
    <div class="relative">
      <PopoverTrigger as-child>
        <button
          type="button"
          class="flex h-9 w-full items-center gap-2 rounded-md border bg-background px-3 pr-10 text-left text-xs font-normal hover:bg-muted/40"
          :class="model.start || model.end ? 'text-foreground' : 'text-muted-foreground'"
        >
          <svg
            class="h-4 w-4 shrink-0"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M8 2v3" />
            <path d="M16 2v3" />
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M3 9h18" />
            <path d="M8 13h.01" />
            <path d="M12 13h.01" />
            <path d="M16 13h.01" />
            <path d="M8 17h.01" />
            <path d="M12 17h.01" />
            <path d="M16 17h.01" />
          </svg>
          <span class="truncate">{{ rangeLabel }}</span>
        </button>
      </PopoverTrigger>

      <button
        v-if="model.start || model.end"
        type="button"
        class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
        title="Очистить период"
        @click="clearRange"
      >
        <svg
          class="h-3.5 w-3.5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="M18 6 6 18" />
          <path d="m6 6 12 12" />
        </svg>
      </button>
    </div>

    <PopoverPortal>
      <PopoverContent
        :side-offset="6"
        align="start"
        class="z-[100] rounded-md border bg-popover p-3 text-popover-foreground shadow-md"
      >
        <RangeCalendarRoot
          v-slot="{ weekDays, grid }"
          v-model="model"
          locale="ru-RU"
          :week-starts-on="1"
          fixed-weeks
        >
          <RangeCalendarHeader class="mb-3 flex items-center justify-between gap-3">
            <RangeCalendarPrev
              class="flex h-8 w-8 items-center justify-center rounded-md border hover:bg-muted"
              title="Предыдущий месяц"
            >
              <svg
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="m15 18-6-6 6-6" />
              </svg>
            </RangeCalendarPrev>
            <RangeCalendarHeading class="text-sm font-medium" />
            <RangeCalendarNext
              class="flex h-8 w-8 items-center justify-center rounded-md border hover:bg-muted"
              title="Следующий месяц"
            >
              <svg
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="m9 18 6-6-6-6" />
              </svg>
            </RangeCalendarNext>
          </RangeCalendarHeader>

          <div class="flex flex-col gap-4 sm:flex-row">
            <RangeCalendarGrid
              v-for="month in grid"
              :key="month.value.toString()"
              class="w-full border-collapse select-none"
            >
              <RangeCalendarGridHead>
                <RangeCalendarGridRow class="grid grid-cols-7">
                  <RangeCalendarHeadCell
                    v-for="day in weekDays"
                    :key="day"
                    class="flex h-8 w-8 items-center justify-center text-xs font-normal text-muted-foreground"
                  >
                    {{ day }}
                  </RangeCalendarHeadCell>
                </RangeCalendarGridRow>
              </RangeCalendarGridHead>
              <RangeCalendarGridBody>
                <RangeCalendarGridRow
                  v-for="(weekDates, index) in month.rows"
                  :key="`week-${index}`"
                  class="grid grid-cols-7"
                >
                  <RangeCalendarCell
                    v-for="weekDate in weekDates"
                    :key="weekDate.toString()"
                    :date="weekDate"
                  >
                    <RangeCalendarCellTrigger
                      :day="weekDate"
                      :month="month.value"
                      class="flex h-8 w-8 items-center justify-center rounded-md text-sm outline-none hover:bg-muted focus:ring-2 focus:ring-ring data-[outside-view]:text-muted-foreground/40 data-[selected]:bg-primary/10 data-[highlighted]:bg-primary/10 data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[today]:font-semibold"
                    />
                  </RangeCalendarCell>
                </RangeCalendarGridRow>
              </RangeCalendarGridBody>
            </RangeCalendarGrid>
          </div>
        </RangeCalendarRoot>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>