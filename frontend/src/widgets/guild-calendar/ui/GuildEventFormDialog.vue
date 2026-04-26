<script setup lang="ts">
import {
  Button,
  Input,
  Label,
} from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';

export type GuildEventFormFields = {
  character_id: number;
  title: string;
  description: string;
  starts_at: string;
  recurrence: 'once' | 'daily' | 'weekly' | 'monthly' | 'yearly';
  recurrence_ends_at: string;
};

const props = defineProps<{
  modalEditingId: number | null;
  formError: string;
  formLoading: boolean;
  loadingMyCharacters: boolean;
  myCharactersInGuild: { id: number; name: string }[];
  canDeleteEvent: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const form = defineModel<GuildEventFormFields>('form', { required: true });

const emit = defineEmits<{
  submit: [];
  cancel: [];
  deleteFromEdit: [];
}>();

function onOpenUpdate(v: boolean) {
  open.value = v;
  if (!v) emit('cancel');
}
</script>

<template>
  <DialogRoot :open="open" @update:open="onOpenUpdate">
    <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            {{ props.modalEditingId != null ? 'Редактировать событие' : 'Новое событие' }}
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Заполните данные события. Поля со звёздочкой обязательны.
          </DialogDescription>

          <form class="flex flex-col gap-4" @submit.prevent="emit('submit')">
            <div v-if="props.modalEditingId == null" class="space-y-2">
              <Label for="event-character">Персонаж (от имени кого создаётся) *</Label>
              <select
                id="event-character"
                v-model.number="form.character_id"
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :disabled="props.loadingMyCharacters"
              >
                <option :value="0">
                  {{ props.loadingMyCharacters ? 'Загрузка…' : props.myCharactersInGuild.length ? '— Выберите персонажа —' : 'Нет персонажей в гильдии' }}
                </option>
                <option
                  v-for="c in props.myCharactersInGuild"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </option>
              </select>
            </div>
            <div class="space-y-2">
              <Label for="event-title">Название *</Label>
              <Input
                id="event-title"
                v-model="form.title"
                type="text"
                placeholder="Название события"
                maxlength="255"
                required
                class="w-full"
              />
            </div>
            <div class="space-y-2">
              <Label for="event-desc">Описание</Label>
              <textarea
                id="event-desc"
                v-model="form.description"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Описание (необязательно)"
              />
            </div>
            <div class="space-y-2">
              <Label for="event-start">Начало *</Label>
              <Input
                id="event-start"
                v-model="form.starts_at"
                type="datetime-local"
                required
                class="w-full"
              />
            </div>
            <div class="space-y-2">
              <Label for="event-recurrence">Повторение</Label>
              <select
                id="event-recurrence"
                v-model="form.recurrence"
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                <option value="once">Один раз</option>
                <option value="daily">Ежедневно</option>
                <option value="weekly">Еженедельно</option>
                <option value="monthly">Ежемесячно</option>
                <option value="yearly">Ежегодно</option>
              </select>
            </div>
            <div v-if="form.recurrence !== 'once'" class="space-y-2">
              <Label for="event-recurrence-end">Повторять до</Label>
              <Input
                id="event-recurrence-end"
                v-model="form.recurrence_ends_at"
                type="datetime-local"
                class="w-full"
              />
            </div>

            <p v-if="props.formError" class="text-sm text-destructive">{{ props.formError }}</p>

            <div class="flex flex-wrap justify-between gap-2 pt-2">
              <div v-if="props.modalEditingId != null && props.canDeleteEvent">
                <Button
                  type="button"
                  variant="ghost"
                  class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                  :disabled="props.formLoading"
                  @click="emit('deleteFromEdit')"
                >
                  Удалить
                </Button>
              </div>
              <div class="flex gap-2 ms-auto">
                <Button type="button" variant="outline" :disabled="props.formLoading" @click="emit('cancel')">
                  Отмена
                </Button>
                <Button type="submit" :disabled="props.formLoading">
                  {{ props.formLoading ? 'Сохранение…' : props.modalEditingId != null ? 'Сохранить' : 'Создать' }}
                </Button>
              </div>
            </div>
          </form>
        </DialogContent>
      </DialogPortal>
    </ClientOnly>
  </DialogRoot>
</template>
