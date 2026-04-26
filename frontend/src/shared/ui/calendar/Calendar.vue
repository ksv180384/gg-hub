<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { cn } from '@/shared/lib/utils';

const MONTH_NAMES = [
  'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
  'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
];

/** Неделя с понедельника. */
const WEEKDAY_NAMES = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

export interface CalendarEvent {
  id: number;
  title: string;
  starts_at: string;
  ends_at: string | null;
}

interface DayCell {
  date: Date | null;
  isCurrentMonth: boolean;
  isToday: boolean;
  day: number;
}

function toDateKey(d: Date): string {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

/** Дата календарного дня из ISO (без сдвига из‑за UTC при `new Date('YYYY-MM-DD')`). */
function parseIsoCalendarDate(iso: string): Date | null {
  const day = iso.slice(0, 10);
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(day);
  if (!m) return null;
  const y = Number(m[1]);
  const mo = Number(m[2]) - 1;
  const d = Number(m[3]);
  if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(d)) return null;
  return new Date(y, mo, d);
}

const props = withDefaults(
  defineProps<{
    modelValue?: Date;
    /** Выбранная мышкой дата (подсвечивается зелёным). */
    selectedDate?: Date | null;
    class?: string;
    showNewEventButton?: boolean;
    events?: CalendarEvent[];
  }>(),
  { showNewEventButton: true, events: () => [] }
);

const emit = defineEmits<{
  'update:modelValue': [value: Date];
  'select-date': [date: Date];
  'new-event': [];
  'view-range': [from: Date, to: Date];
  'click-event': [event: CalendarEvent];
}>();

const viewDate = ref(props.modelValue ? new Date(props.modelValue) : new Date());

function startOfMonth(d: Date) {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

function endOfMonth(d: Date) {
  return new Date(d.getFullYear(), d.getMonth() + 1, 0);
}

/** Первая и последняя дата, попадающие в отрисованную сетку (включая дни прошлого/следующего месяца). */
function visibleGridRange(anchor: Date): { from: Date; to: Date } {
  const monthStart = startOfMonth(anchor);
  const monthEnd = endOfMonth(anchor);
  const padding = (monthStart.getDay() + 6) % 7;
  const from = new Date(monthStart);
  from.setDate(from.getDate() - padding);
  const daysInMonth = monthEnd.getDate();
  const totalLeadingAndMonth = padding + daysInMonth;
  const trailing = totalLeadingAndMonth % 7 === 0 ? 0 : 7 - (totalLeadingAndMonth % 7);
  const to = new Date(anchor.getFullYear(), anchor.getMonth(), daysInMonth + trailing);
  return { from, to };
}

function isSameDay(a: Date, b: Date) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

function isSameMonth(a: Date, b: Date) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth();
}

function isSelectedDay(cell: DayCell): boolean {
  if (!cell.date || !props.selectedDate) return false;
  return isSameDay(cell.date, props.selectedDate);
}

const monthTitle = computed(() => {
  return `${MONTH_NAMES[viewDate.value.getMonth()]} ${viewDate.value.getFullYear()}`;
});

/** Текущая дата для кнопки (например «1 марта 2026»). */
const todayLabel = computed(() => {
  const d = new Date();
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
});

const weeks = computed((): DayCell[][] => {
  const start = startOfMonth(viewDate.value);
  const end = endOfMonth(viewDate.value);
  const today = new Date();
  // 0 = понедельник, 6 = воскресенье
  const padding = (start.getDay() + 6) % 7;
  const daysInMonth = end.getDate();

  const result: DayCell[][] = [];
  let week: DayCell[] = [];

  for (let i = 0; i < padding; i++) {
    const prevMonth = new Date(start);
    prevMonth.setDate(prevMonth.getDate() - (padding - i));
    week.push({
      date: prevMonth,
      isCurrentMonth: false,
      isToday: isSameDay(prevMonth, today),
      day: prevMonth.getDate(),
    });
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), day);
    week.push({
      date,
      isCurrentMonth: true,
      isToday: isSameDay(date, today),
      day,
    });
    if (week.length === 7) {
      result.push(week);
      week = [];
    }
  }

  if (week.length > 0) {
    let nextDay = 1;
    while (week.length < 7) {
      const date = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, nextDay);
      week.push({
        date,
        isCurrentMonth: false,
        isToday: isSameDay(date, today),
        day: nextDay,
      });
      nextDay++;
    }
    result.push(week);
  }

  return result;
});

const eventsByDay = computed(() => {
  const map: Record<string, CalendarEvent[]> = {};
  for (const ev of props.events) {
    const start = ev.starts_at.slice(0, 10);
    const end = ev.ends_at ? ev.ends_at.slice(0, 10) : start;
    const startD = parseIsoCalendarDate(start);
    const endD = parseIsoCalendarDate(end);
    if (!startD || !endD) continue;
    for (let d = new Date(startD); d <= endD; d.setDate(d.getDate() + 1)) {
      const key = toDateKey(d);
      if (!map[key]) map[key] = [];
      map[key].push(ev);
    }
  }
  return map;
});

function getEventsForCell(cell: DayCell): CalendarEvent[] {
  if (!cell.date) return [];
  return eventsByDay.value[toDateKey(cell.date)] ?? [];
}

watch(
  viewDate,
  () => {
    const { from, to } = visibleGridRange(viewDate.value);
    emit('view-range', from, to);
  },
  { immediate: true }
);

function goToToday() {
  viewDate.value = new Date();
  emit('update:modelValue', viewDate.value);
}

function prevMonth() {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() - 1);
}

function nextMonth() {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1);
}

function prevYear() {
  viewDate.value = new Date(viewDate.value.getFullYear() - 1, viewDate.value.getMonth());
}

function nextYear() {
  viewDate.value = new Date(viewDate.value.getFullYear() + 1, viewDate.value.getMonth());
}

function onSelectDay(cell: DayCell) {
  if (!cell.date) return;
  emit('select-date', cell.date);
  emit('update:modelValue', cell.date);
}

function onCellClick(e: MouseEvent, cell: DayCell) {
  if (!cell.date) return;
  const target = e.target as HTMLElement;
  const eventId = target.closest('[data-event-id]')?.getAttribute('data-event-id');
  if (eventId) {
    const ev = props.events.find((x) => String(x.id) === eventId);
    if (ev) {
      e.preventDefault();
      e.stopPropagation();
      emit('click-event', ev);
      return;
    }
  }
  emit('select-date', cell.date);
  emit('update:modelValue', cell.date);
}

function onNewEvent() {
  emit('new-event');
}
</script>

<template>
  <div :class="cn('flex flex-col gap-4', props.class)">
    <!-- Header: текущая дата (клик — переход на сегодня) | стрелки | месяц и год -->
    <div class="flex flex-wrap items-center justify-between gap-2 sm:gap-4">
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
          @click="goToToday"
          :title="'Перейти на сегодня'"
        >
          {{ todayLabel }}
        </button>
        <div class="flex items-center gap-0.5">
          <button
            type="button"
            class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            aria-label="Предыдущий год"
            title="Предыдущий год"
            @click="prevYear"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>
          </button>
          <button
            type="button"
            class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            aria-label="Предыдущий месяц"
            title="Предыдущий месяц"
            @click="prevMonth"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          </button>
          <button
            type="button"
            class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            aria-label="Следующий месяц"
            title="Следующий месяц"
            @click="nextMonth"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </button>
          <button
            type="button"
            class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            aria-label="Следующий год"
            title="Следующий год"
            @click="nextYear"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/></svg>
          </button>
        </div>
      </div>
      <h2 class="text-lg font-semibold">
        {{ monthTitle }}
      </h2>
    </div>

    <!-- Calendar grid -->
    <div class="rounded-lg border border-border bg-card">
      <div class="grid grid-cols-7 border-b border-border">
        <div
          v-for="name in WEEKDAY_NAMES"
          :key="name"
          class="border-r border-border px-1 py-2 text-center text-xs font-medium text-muted-foreground last:border-r-0 sm:px-2 sm:py-3"
        >
          {{ name }}
        </div>
      </div>
      <div
        v-for="(week, wi) in weeks"
        :key="wi"
        class="grid grid-cols-7 border-b border-border last:border-b-0"
      >
        <button
          v-for="(cell, di) in week"
          :key="di"
          type="button"
          :class="cn(
            'min-h-16 border-r border-border p-1.5 text-left transition-colors last:border-r-0 hover:bg-accent/50 sm:min-h-24 sm:p-2',
            !cell.isCurrentMonth && 'bg-muted/30 text-muted-foreground',
            cell.isToday && !isSelectedDay(cell) && 'bg-muted/50 font-medium',
            isSelectedDay(cell) && 'bg-green-50 font-medium text-green-800 dark:bg-green-950/25 dark:text-green-200',
            cell.isCurrentMonth && !cell.isToday && !isSelectedDay(cell) && 'text-foreground'
          )"
          @click="(e: MouseEvent) => onCellClick(e, cell)"
        >
          <span class="text-xs sm:text-sm">{{ cell.day }}</span>
          <div v-if="getEventsForCell(cell).length" class="mt-1 space-y-0.5">
            <div
              v-for="ev in getEventsForCell(cell).slice(0, 3)"
              :key="ev.id"
              :data-event-id="String(ev.id)"
              class="truncate rounded bg-primary/20 px-1 py-0.5 text-xs text-primary hover:bg-primary/30"
            >
              {{ ev.title }}
            </div>
            <div
              v-if="getEventsForCell(cell).length > 3"
              class="text-xs text-muted-foreground"
            >
              +{{ getEventsForCell(cell).length - 3 }}
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
