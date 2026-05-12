<script setup lang="ts">
import { Button, Input, Label } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { cn } from '@/shared/lib/utils';
import type { GuildBankGrantForm } from '@/features/guild-bank';
import type { GuildBankItem } from '@/shared/api/guildBankApi';
import type { GuildRosterMember } from '@/shared/api/guildsApi';
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';

defineProps<{
  selectedItem: GuildBankItem | null;
  selectedRosterMember: GuildRosterMember | null;
  filteredRosterMembers: GuildRosterMember[];
  rosterLoading: boolean;
  rosterError: string;
  formError: string;
  saving: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const form = defineModel<GuildBankGrantForm>('form', { required: true });
const memberSelectOpen = defineModel<boolean>('memberSelectOpen', { required: true });
const memberSearch = defineModel<string>('memberSearch', { required: true });

const emit = defineEmits<{
  save: [];
}>();
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-lg rounded-xl border border-border bg-card text-card-foreground shadow-lg">
      <div class="border-b border-border px-4 py-3">
        <div class="text-base font-semibold">Выдать предмет</div>
        <div v-if="selectedItem" class="text-xs text-muted-foreground mt-0.5">{{ selectedItem.name }}</div>
      </div>
      <div class="space-y-4 px-4 py-4">
        <div class="space-y-2">
          <Label for="grant-received">Персонаж *</Label>

          <PopoverRoot v-model:open="memberSelectOpen">
            <PopoverTrigger
              id="grant-received"
              type="button"
              :class="cn(
                'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm ring-offset-background transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:pointer-events-none disabled:opacity-50 [&>span]:line-clamp-1',
                saving ? 'opacity-60 pointer-events-none' : ''
              )"
            >
              <span
                class="min-w-0 truncate text-left"
                :class="!selectedRosterMember && 'text-muted-foreground'"
              >
                {{ selectedRosterMember?.name ?? 'Выберите персонажа' }}
              </span>
              <span class="ml-2 shrink-0 opacity-50" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m6 9 6 6 6-6" />
                </svg>
              </span>
            </PopoverTrigger>

            <ClientOnly>
              <PopoverPortal>
                <PopoverContent
                  side="bottom"
                  align="start"
                  :side-offset="4"
                  :class="cn(
                    'z-50 w-[var(--radix-popover-trigger-width)] max-h-[min(20rem,var(--radix-popover-content-available-height))] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                    'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    'data-[side=bottom]:slide-in-from-top-2'
                  )"
                >
                  <div class="p-1.5 border-b">
                    <Input
                      v-model="memberSearch"
                      type="text"
                      placeholder="Поиск по имени..."
                      class="h-8 text-sm"
                      @keydown.stop
                    />
                  </div>
                  <div class="max-h-[14rem] overflow-y-auto p-1">
                    <p v-if="rosterLoading" class="px-2 py-3 text-sm text-muted-foreground">
                      Загрузка состава…
                    </p>
                    <p v-else-if="rosterError" class="px-2 py-3 text-sm text-destructive">
                      {{ rosterError }}
                    </p>
                    <template v-else>
                      <button
                        v-for="member in filteredRosterMembers"
                        :key="member.character_id"
                        type="button"
                        :class="cn(
                          'relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground',
                          Number(form.received_by_character_id) === member.character_id && 'bg-accent text-accent-foreground'
                        )"
                        @click="
                          form.received_by_character_id = String(member.character_id);
                          memberSelectOpen = false;
                        "
                      >
                        {{ member.name }}
                      </button>
                      <p v-if="filteredRosterMembers.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
                        Ничего не найдено
                      </p>
                    </template>
                  </div>
                </PopoverContent>
              </PopoverPortal>
            </ClientOnly>
          </PopoverRoot>
        </div>
        <div class="space-y-2">
          <Label for="grant-reason">За что</Label>
          <textarea
            id="grant-reason"
            v-model="form.reason"
            rows="3"
            class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            placeholder="Необязательно"
          />
        </div>
        <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
        <Button variant="outline" :disabled="saving" @click="open = false">Отмена</Button>
        <Button :disabled="saving" @click="emit('save')">{{ saving ? 'Выдача…' : 'Выдать' }}</Button>
      </div>
    </div>
  </div>
</template>
