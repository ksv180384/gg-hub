<script setup lang="ts">
import { reactive } from 'vue';
import {
  formatGuildActivityDate,
  guildActivityCategoryLabel,
  useGuildActivityLog,
} from '@/features/guild-activity/model/useGuildActivityLog';
import {
  Button,
  DateRangePicker,
  Input,
  Label,
  Select,
  Spinner,
} from '@/shared/ui';

const model = reactive(useGuildActivityLog());
</script>

<template>
  <div>
    <div class="w-full space-y-5">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">
          История гильдии
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Действия участников и системные изменения во всех разделах гильдии
        </p>
      </div>

      <div class="rounded-lg border border-border bg-background p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-[minmax(14rem,2fr)_minmax(12rem,1fr)_12rem_15rem_2.25rem]">
          <div class="grid gap-1.5">
            <Label for="guild-activity-search">
              Поиск
            </Label>
            <Input
              id="guild-activity-search"
              v-model="model.searchFilter"
              placeholder="Описание или объект"
            />
          </div>

          <div class="grid gap-1.5">
            <Label for="guild-activity-actor">
              Инициатор
            </Label>
            <Input
              id="guild-activity-actor"
              v-model="model.actorNameFilter"
              placeholder="Имя пользователя"
            />
          </div>

          <div class="grid gap-1.5">
            <Label for="guild-activity-category">
              Раздел
            </Label>
            <Select
              id="guild-activity-category"
              v-model="model.categoryFilter"
              :options="model.categoryOptions"
              placeholder="Все разделы"
            />
          </div>

          <div class="grid gap-1.5">
            <Label>
              Период
            </Label>
            <DateRangePicker v-model="model.dateRangeFilter" />
          </div>

          <div class="flex items-end">
            <Button
              variant="outline"
              size="icon"
              :disabled="!model.hasActiveFilters"
              title="Сбросить фильтры"
              aria-label="Сбросить фильтры"
              @click="model.resetFilters"
            >
              <svg
                class="size-4"
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
            </Button>
          </div>
        </div>
      </div>

      <div
        v-if="model.error"
        class="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive"
      >
        {{ model.error }}
      </div>

      <div
        v-if="model.loading"
        class="flex min-h-40 items-center justify-center gap-2 text-sm text-muted-foreground"
      >
        <Spinner class="h-4 w-4" />
        Загрузка…
      </div>

      <p
        v-else-if="model.logs.length === 0"
        class="rounded-lg border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground"
      >
        {{ model.hasActiveFilters ? 'По заданным фильтрам ничего не найдено.' : 'История гильдии пока пуста.' }}
      </p>

      <ul
        v-else
        class="overflow-hidden rounded-lg border border-border bg-background shadow-sm"
      >
        <li
          v-for="entry in model.logs"
          :key="entry.id"
          class="border-b border-border px-4 py-3 last:border-b-0"
        >
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <p class="text-sm font-medium text-foreground">
                {{ entry.description }}
              </p>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ entry.actor?.name || 'Система' }}
                <span aria-hidden="true"> · </span>
                {{ formatGuildActivityDate(entry.created_at) }}
              </p>
            </div>

            <span class="w-fit shrink-0 rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground">
              {{ guildActivityCategoryLabel(entry.category) }}
            </span>
          </div>
        </li>
      </ul>

      <div
        v-if="model.total > 0"
        class="flex flex-wrap items-center justify-between gap-3"
      >
        <p class="text-sm text-muted-foreground">
          Всего записей: {{ model.total }}
        </p>

        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="model.loading || model.currentPage <= 1"
            @click="model.loadPage(model.currentPage - 1)"
          >
            Назад
          </Button>
          <span class="text-sm text-muted-foreground">
            {{ model.currentPage }} из {{ model.lastPage }}
          </span>
          <Button
            variant="outline"
            size="sm"
            :disabled="model.loading || model.currentPage >= model.lastPage"
            @click="model.loadPage(model.currentPage + 1)"
          >
            Вперёд
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
