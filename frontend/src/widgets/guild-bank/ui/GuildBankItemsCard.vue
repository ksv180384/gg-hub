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
  <Card>
    <CardHeader class="flex flex-row items-center justify-between gap-2 space-y-0 p-2">
      <CardTitle class="text-base">Предметы</CardTitle>
      <div v-if="canAddItems" class="flex flex-wrap items-center gap-2">
        <Button size="sm" variant="outline" @click="emit('openTiers')">Тиры</Button>
        <Button size="sm" @click="emit('create')">Добавить</Button>
      </div>
    </CardHeader>
    <CardContent class="space-y-2 px-2 pb-2 pt-0">
      <p v-if="!items.length" class="text-sm text-muted-foreground">Пока нет предметов.</p>
      <div v-else class="space-y-2">
        <Input
          v-model="itemsSearch"
          type="text"
          placeholder="Поиск по названию..."
          class="h-9"
        />

        <p v-if="filteredItems.length === 0" class="text-sm text-muted-foreground">
          Ничего не найдено.
        </p>

        <ul v-else class="space-y-2">
          <li
            v-for="item in filteredItems"
            :key="item.id"
            :class="[
              'overflow-hidden rounded-xl border bg-card transition-colors cursor-pointer',
              item.tier?.color && 'border-l-4',
              item.id === selectedItemId
                ? 'border-primary/70 shadow-sm'
                : 'border-border hover:border-border/80 hover:bg-accent/20',
            ]"
            :style="item.tier?.color ? { borderLeftColor: item.tier.color } : undefined"
            @click="emit('select', item.id)"
          >
            <div class="flex items-start justify-between gap-2 px-3 py-3">
              <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold leading-tight">{{ item.name }}</div>
                <div v-if="item.tier" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                  <span
                    class="inline-flex rounded-full px-1.5 py-0 text-[10px] font-medium leading-4"
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
                  class="h-8 w-8 shrink-0 cursor-pointer inline-flex items-center justify-center"
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
                      class="h-8 w-8 cursor-pointer inline-flex items-center justify-center"
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

            <div class="border-t border-border/70 bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
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
