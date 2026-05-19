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
</script>

<template>
  <Card class="rounded-lg border-border bg-background shadow-sm">
    <CardHeader class="flex flex-row items-center justify-between gap-2 space-y-0 p-3 pb-2">
      <CardTitle class="text-base">Предметы</CardTitle>
      <div v-if="canAddItems" class="flex flex-wrap items-center gap-2">
        <Button class="h-9" variant="outline" @click="emit('openTiers')">Тиры</Button>
        <Button class="h-9" @click="emit('create')">Добавить</Button>
      </div>
    </CardHeader>
    <CardContent class="space-y-3 px-3 pb-3 pt-0">
      <p v-if="!items.length" class="text-sm text-muted-foreground">Пока нет предметов.</p>
      <div v-else class="space-y-3">
        <label class="relative block">
          <svg
            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
          <Input
            v-model="itemsSearch"
            type="text"
            placeholder="Поиск по названию"
            class="h-9 pl-9"
          />
        </label>

        <p v-if="filteredItems.length === 0" class="text-sm text-muted-foreground">
          Ничего не найдено.
        </p>

        <ul v-else class="overflow-hidden rounded-lg border border-border bg-background">
          <li
            v-for="item in filteredItems"
            :key="item.id"
            :class="[
              'cursor-pointer overflow-hidden border-b border-b-border bg-background transition-colors last:border-b-0',
              item.tier?.color ? 'border-l-4' : 'border-l-0',
              item.id === selectedItemId
                ? 'bg-primary/5 shadow-[inset_0_0_0_1px_hsl(var(--primary)/0.45)]'
                : 'hover:bg-muted/20',
            ]"
            :style="item.tier?.color ? { borderLeftColor: item.tier.color } : undefined"
            @click="emit('select', item.id)"
          >
            <div class="flex items-start justify-between gap-2 px-4 py-3">
              <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold leading-tight">{{ item.name }}</div>
                <div v-if="item.tier" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                  <span
                    class="inline-flex min-w-5 justify-center rounded-full px-1.5 py-0 text-[10px] font-medium leading-5"
                    :style="item.tier.color ? { backgroundColor: item.tier.color, color: '#fff' } : undefined"
                    :class="!item.tier.color && 'bg-white text-foreground border border-border'"
                  >
                    {{ item.tier.name }}
                  </span>
                </div>
              </div>
              <div
                v-if="canAddItems || canDeleteItems"
                class="flex shrink-0 items-center gap-0.5"
                @click.stop
              >
                <Button
                  v-if="canAddItems"
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="inline-flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center text-muted-foreground hover:text-foreground"
                  title="Добавить предметы"
                  aria-label="Добавить предметы"
                  @click="emit('restock', item)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="shrink-0"
                    aria-hidden="true"
                  >
                    <line x1="15" x2="15" y1="12" y2="18" />
                    <line x1="12" x2="18" y1="15" y2="15" />
                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
                  </svg>
                </Button>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="inline-flex h-8 w-8 cursor-pointer items-center justify-center text-muted-foreground hover:text-foreground"
                      aria-label="Действия с предметом"
                      title="Действия"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                      >
                        <circle cx="12" cy="5" r="2" />
                        <circle cx="12" cy="12" r="2" />
                        <circle cx="12" cy="19" r="2" />
                      </svg>
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="min-w-44">
                    <DropdownMenuItem v-if="canAddItems" @click="emit('edit', item)">
                      Редактировать
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      v-if="canDeleteItems"
                      class="text-destructive focus:text-destructive"
                      :disabled="itemHasActiveGrants(item)"
                      :title="
                        itemHasActiveGrants(item)
                          ? 'Нельзя удалить: есть активные выдачи. Сначала отмените их в истории.'
                          : undefined
                      "
                      @click="emit('delete', item)"
                    >
                      Удалить
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            <div class="border-t border-border/80 bg-muted/30 px-4 py-2 text-xs text-muted-foreground">
              <span>Осталось: {{ item.quantity ?? '∞' }}</span>
              <span> · </span>
              <span>Выдано: {{ item.grants_count ?? 0 }}</span>
              <template v-if="dkpEnabled && item.dkp_cost != null">
                <span> · </span>
                <span>ДКП: {{ item.dkp_cost }}</span>
              </template>
            </div>
          </li>
        </ul>
      </div>
    </CardContent>
  </Card>
</template>
