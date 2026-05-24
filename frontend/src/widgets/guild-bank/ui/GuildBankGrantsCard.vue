<script setup lang="ts">
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Tooltip } from '@/shared/ui';
import { formatBankDateTime } from '@/features/guild-bank';
import type { GuildBankGrant, GuildBankItem } from '@/shared/api/guildBankApi';

defineProps<{
  selectedItem: GuildBankItem | null;
  grants: GuildBankGrant[];
  filteredGrants: GuildBankGrant[];
  grantsLoading: boolean;
  grantsError: string;
  canGrantItems: boolean;
  revokingGrantId: number | null;
  dkpEnabled: boolean;
}>();

const grantsSearch = defineModel<string>('grantsSearch', { required: true });

const emit = defineEmits<{
  grant: [];
  revoke: [grant: GuildBankGrant];
}>();

function initials(name: string | undefined) {
  const value = name?.trim();
  if (!value) return '?';
  return value.slice(0, 2).toUpperCase();
}

function quantityLabel(item: GuildBankItem) {
  return item.quantity == null ? '∞' : item.quantity.toLocaleString('ru-RU');
}

function chargedLabel(grant: GuildBankGrant, selectedItem: GuildBankItem) {
  const value = grant.dkp_charged ?? selectedItem.dkp_cost ?? 0;
  return value > 0 ? `-${value} ДКП` : '0 ДКП';
}
</script>

<template>
  <Card class="overflow-hidden rounded-lg border-border bg-card shadow-sm">
    <CardHeader class="space-y-3 border-b border-border px-4 py-3">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <CardTitle class="truncate text-base">
            История выдач
            <span v-if="selectedItem" class="font-normal text-muted-foreground">— {{ selectedItem.name }}</span>
          </CardTitle>
        </div>
        <Button v-if="selectedItem && canGrantItems" class="h-9 shrink-0" @click="emit('grant')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
          Выдать
        </Button>
      </div>

      <label class="relative block max-w-sm">
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
          v-model="grantsSearch"
          type="text"
          placeholder="Поиск по пользователям"
          class="h-9 pl-9"
        />
      </label>
    </CardHeader>

    <CardContent class="space-y-3 p-4">
      <p v-if="!selectedItem" class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
        Выберите предмет слева, чтобы посмотреть историю выдач.
      </p>

      <template v-else>
        <div class="grid grid-cols-[minmax(0,1fr)_88px_104px_76px] items-center gap-3 rounded-lg border border-border bg-card p-3">
          <div class="min-w-0 flex items-center gap-3">
            <span
              v-if="selectedItem.tier"
              class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-xs font-bold text-white"
              :style="{ backgroundColor: selectedItem.tier.color ?? '#64748b' }"
            >
              {{ selectedItem.tier.name.slice(0, 2) }}
            </span>
            <span v-else class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted text-xs font-bold text-muted-foreground">
              —
            </span>
            <div class="min-w-0 truncate text-sm font-semibold">{{ selectedItem.name }}</div>
          </div>
          <div class="text-xs text-muted-foreground">
            Осталось:
            <div class="mt-0.5 text-sm font-bold tabular-nums text-foreground">{{ quantityLabel(selectedItem) }}</div>
          </div>
          <div class="text-xs text-muted-foreground">
            Стоимость:
            <div class="mt-0.5 text-sm font-bold tabular-nums text-foreground">{{ selectedItem.dkp_cost ?? 0 }} ДКП</div>
          </div>
          <div class="text-xs text-muted-foreground">
            Выдано:
            <div class="mt-0.5 text-sm font-bold tabular-nums text-foreground">{{ selectedItem.grants_count ?? grants.length }}</div>
          </div>
        </div>

        <p v-if="grantsLoading" class="text-sm text-muted-foreground">Загрузка истории...</p>
        <p v-else-if="grantsError" class="text-sm text-destructive">{{ grantsError }}</p>
        <p v-else-if="!grants.length" class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
          По этому предмету пока нет выдач.
        </p>
        <p v-else-if="filteredGrants.length === 0" class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
          Ничего не найдено.
        </p>

        <ul v-else class="overflow-hidden rounded-lg border border-border">
          <li
            v-for="grant in filteredGrants"
            :key="grant.id"
            class="grid grid-cols-[minmax(220px,1fr)_82px_36px_36px] items-center gap-3 border-b border-border bg-card px-3 py-3 last:border-b-0"
          >
            <div class="min-w-0 flex items-center gap-3">
              <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border bg-muted text-xs font-bold text-muted-foreground"
                aria-hidden="true"
              >
                {{ initials(grant.received_by_character?.name) }}
              </div>
              <div class="min-w-0">
                <div class="flex min-w-0 items-baseline gap-2">
                  <span
                    class="min-w-0 truncate text-sm font-semibold"
                    :title="grant.received_by_character?.name ?? `Персонаж #${grant.received_by_character_id}`"
                  >
                    {{ grant.received_by_character?.name ?? `Персонаж #${grant.received_by_character_id}` }}
                  </span>
                  <span class="shrink-0 text-xs text-muted-foreground">{{ formatBankDateTime(grant.granted_at) }}</span>
                </div>
                <div class="mt-0.5 truncate text-xs text-muted-foreground">
                  выдал: {{ grant.granted_by_character?.name ?? '—' }}
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <span
                v-if="dkpEnabled"
                class="rounded-md bg-red-50 px-2 py-1 text-xs font-bold tabular-nums text-red-600"
              >
                {{ chargedLabel(grant, selectedItem) }}
              </span>
            </div>

            <div class="flex justify-center">
              <Tooltip
                v-if="grant.reason?.trim()"
                :content="grant.reason.trim()"
                side="top"
                class="max-w-sm whitespace-pre-line text-left"
              >
                <button
                  type="button"
                  class="inline-flex h-7 w-7 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                  aria-label="Комментарий к выдаче"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 16v-4" />
                    <path d="M12 8h.01" />
                  </svg>
                </button>
              </Tooltip>
              <span v-else class="text-sm text-muted-foreground">—</span>
            </div>

            <Button
              v-if="canGrantItems"
              type="button"
              variant="ghost"
              size="icon"
              class="h-8 w-8 border border-border bg-background text-muted-foreground hover:text-destructive"
              title="Вернуть предмет"
              aria-label="Вернуть предмет"
              :disabled="revokingGrantId === grant.id"
              @click="emit('revoke', grant)"
            >
              <svg
                v-if="revokingGrantId === grant.id"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="animate-spin"
                aria-hidden="true"
              >
                <path d="M21 12a9 9 0 1 1-6.219-8.56" />
              </svg>
              <svg
                v-else
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                <path d="M3 3v5h5" />
              </svg>
            </Button>
            <span v-else class="text-right text-sm text-muted-foreground">—</span>
          </li>
        </ul>
      </template>
    </CardContent>
  </Card>
</template>
