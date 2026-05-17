<script setup lang="ts">
import { Button, Card, CardContent, CardHeader, CardTitle, Input } from '@/shared/ui';
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
}>();

const grantsSearch = defineModel<string>('grantsSearch', { required: true });

const emit = defineEmits<{
  grant: [];
  revoke: [grant: GuildBankGrant];
}>();
</script>

<template>
  <Card>
    <CardHeader class="flex flex-row items-center justify-between gap-2 space-y-0 p-2">
      <CardTitle class="text-base">
        История выдач
        <span v-if="selectedItem" class="text-muted-foreground font-normal">— {{ selectedItem.name }}</span>
      </CardTitle>
      <Button v-if="selectedItem && canGrantItems" size="sm" @click="emit('grant')">Выдать</Button>
    </CardHeader>
    <CardContent class="space-y-2 px-2 pb-2 pt-0">
      <p v-if="!selectedItem" class="text-sm text-muted-foreground">Выберите предмет слева.</p>
      <template v-else>
        <p v-if="grantsLoading" class="text-sm text-muted-foreground">Загрузка истории…</p>
        <p v-else-if="grantsError" class="text-sm text-destructive">{{ grantsError }}</p>
        <p v-else-if="!grants.length" class="text-sm text-muted-foreground">
          Пока нет выдач.
        </p>
        <div v-else class="space-y-3">
          <div class="flex items-center gap-2">
            <Input
              v-model="grantsSearch"
              type="text"
              placeholder="Поиск по нику получателя..."
              class="h-9"
            />
          </div>

          <p v-if="filteredGrants.length === 0" class="text-sm text-muted-foreground">
            Ничего не найдено.
          </p>

          <ul v-else class="space-y-2">
            <li
              v-for="grant in filteredGrants"
              :key="grant.id"
              class="rounded border p-3"
            >
              <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="text-sm font-medium truncate">
                      {{ grant.received_by_character?.name ?? `Персонаж #${grant.received_by_character_id}` }}
                    </div>
                  </div>
                  <div class="text-xs text-muted-foreground">
                    {{ formatBankDateTime(grant.granted_at) }}
                    <span v-if="grant.granted_by_character?.name"> · выдал: {{ grant.granted_by_character.name }}</span>
                  </div>
                </div>
                <Button
                  v-if="canGrantItems"
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 shrink-0 self-start text-muted-foreground hover:text-destructive"
                  title="Отменить выдачу"
                  aria-label="Отменить выдачу"
                  :disabled="revokingGrantId === grant.id"
                  @click="emit('revoke', grant)"
                >
                  <svg
                    v-if="revokingGrantId === grant.id"
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="animate-spin"
                    aria-hidden="true"
                  >
                    <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                  </svg>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                    <path d="M3 3v5h5" />
                  </svg>
                </Button>
              </div>
              <div class="mt-2 text-sm whitespace-pre-wrap">
                {{ grant.reason?.trim() ? grant.reason : '—' }}
              </div>
            </li>
          </ul>
        </div>
      </template>
    </CardContent>
  </Card>
</template>
