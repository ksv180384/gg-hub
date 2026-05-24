<script setup lang="ts">
import {
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  Input,
} from '@/shared/ui';
import { itemHasActiveGrants } from '@/features/guild-bank';
import type { GuildBankItem } from '@/shared/api/guildBankApi';

defineProps<{
  items: GuildBankItem[];
  filteredItems: GuildBankItem[];
  selectedItemId: number | null;
  canAddItems: boolean;
  canDeleteItems: boolean;
  dkpEnabled: boolean;
}>();

const itemsSearch = defineModel<string>('itemsSearch', { required: true });

const emit = defineEmits<{
  create: [];
  openTiers: [];
  select: [itemId: number];
  restock: [item: GuildBankItem];
  edit: [item: GuildBankItem];
  delete: [item: GuildBankItem];
}>();

function quantityLabel(item: GuildBankItem) {
  return item.quantity == null ? '∞' : item.quantity.toLocaleString('ru-RU');
}
</script>

<template>
  <Card class="overflow-hidden rounded-lg border-border bg-card shadow-sm">
    <CardHeader class="space-y-3 border-b border-border px-4 py-3">
      <CardTitle class="text-base">Предметы</CardTitle>

      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <label class="relative block min-w-0 flex-1">
          <svg
            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
          >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
          <Input
            v-model="itemsSearch"
            type="text"
            placeholder="Поиск по предметам"
            class="h-9 pl-9"
          />
        </label>
        <div v-if="canAddItems" class="flex shrink-0 flex-wrap items-center gap-2">
          <Button class="h-9" variant="outline" @click="emit('openTiers')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="m12 3 8 4-8 4-8-4 8-4Z" />
              <path d="m4 12 8 4 8-4" />
              <path d="m4 17 8 4 8-4" />
            </svg>
            <span>Тиры</span>
          </Button>
          <Button class="h-9" @click="emit('create')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M5 12h14" />
              <path d="M12 5v14" />
            </svg>
            <span>Добавить</span>
          </Button>
        </div>
      </div>
    </CardHeader>

    <CardContent class="p-0">
      <div v-if="!items.length" class="rounded-lg border border-dashed border-border p-8 text-center">
        <p class="text-sm font-medium">В хранилище пока нет предметов</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Добавьте первый предмет, задайте тир, количество и стоимость ДКП.
        </p>
      </div>

      <p v-else-if="filteredItems.length === 0" class="p-8 text-center text-sm text-muted-foreground">
        Ничего не найдено.
      </p>

      <div v-else class="overflow-hidden">
        <div class="grid grid-cols-[minmax(0,1fr)_64px_64px_54px_58px_76px] gap-2 border-b border-border bg-muted/40 px-4 py-2 text-xs font-medium text-muted-foreground">
          <div class="flex items-center gap-1">
            <span>Предмет</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3" aria-hidden="true">
              <path d="m7 15 5 5 5-5" />
              <path d="m7 9 5-5 5 5" />
            </svg>
          </div>
          <div>Тир</div>
          <div class="text-right">Кол-во</div>
          <div class="text-right" title="Стоимость ДКП">ДКП</div>
          <div class="text-right">Выдано</div>
          <div class="text-right">Действия</div>
        </div>

        <div
          v-for="item in filteredItems"
          :key="item.id"
          role="button"
          tabindex="0"
          :class="[
            'grid grid-cols-[minmax(0,1fr)_64px_64px_54px_58px_76px] gap-2 border-b border-l-4 border-b-border bg-card px-4 py-3 text-left transition-colors last:border-b-0',
            item.id === selectedItemId ? 'bg-primary/5 ring-1 ring-inset ring-primary/20' : 'hover:bg-muted/30',
          ]"
          :style="{ borderLeftColor: item.tier?.color ?? 'hsl(var(--primary))' }"
          @click="emit('select', item.id)"
          @keydown.enter.prevent="emit('select', item.id)"
          @keydown.space.prevent="emit('select', item.id)"
        >
          <div class="min-w-0">
            <div class="flex min-w-0 items-start gap-3">
              <div class="min-w-0">
                <div class="truncate text-sm font-semibold leading-5">{{ item.name }}</div>
                <p class="mt-0.5 truncate text-xs leading-5 text-muted-foreground">
                  {{ item.description?.trim() || 'Описание не заполнено' }}
                </p>
              </div>
            </div>
          </div>

          <div class="flex items-center">
            <span
              v-if="item.tier"
              class="inline-flex max-w-full items-center rounded-md px-1.5 py-1 text-xs font-semibold text-white"
              :style="{ backgroundColor: item.tier.color ?? '#64748b' }"
            >
              <span class="truncate">{{ item.tier.name }}</span>
            </span>
            <span v-else class="text-xs text-muted-foreground">Без тира</span>
          </div>

          <div class="flex items-center justify-end">
            <span class="text-sm font-semibold tabular-nums">{{ quantityLabel(item) }}</span>
          </div>

          <div class="flex items-center justify-end">
            <span class="text-sm font-semibold tabular-nums">
              {{ dkpEnabled && item.dkp_cost != null ? item.dkp_cost : '—' }}
            </span>
          </div>

          <div class="flex items-center justify-end">
            <span class="text-sm font-semibold tabular-nums">{{ item.grants_count ?? 0 }}</span>
          </div>

          <div class="flex items-center justify-end">
            <div class="flex items-center gap-2">
              <div
                v-if="canAddItems || canDeleteItems"
                class="flex items-center gap-1"
                @click.stop
              >
                <Button
                  v-if="canAddItems"
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 border border-border bg-background"
                  title="Пополнить остаток"
                  aria-label="Пополнить остаток"
                  @click="emit('restock', item)"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-3.3-6.96" />
                    <path d="M21 3v6h-6" />
                  </svg>
                </Button>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="h-8 w-8 border border-border bg-background"
                      aria-label="Действия с предметом"
                      title="Действия"
                    >
                      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="12" cy="5" r="2" />
                        <circle cx="12" cy="12" r="2" />
                        <circle cx="12" cy="19" r="2" />
                      </svg>
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="min-w-48">
                    <DropdownMenuItem v-if="canAddItems" @click="emit('edit', item)">
                      Редактировать
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      v-if="canDeleteItems"
                      class="text-destructive focus:text-destructive"
                      :disabled="itemHasActiveGrants(item)"
                      :title="itemHasActiveGrants(item) ? 'Сначала отмените активные выдачи.' : undefined"
                      @click="emit('delete', item)"
                    >
                      Удалить
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
              <span v-else class="text-sm text-muted-foreground">—</span>
            </div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
