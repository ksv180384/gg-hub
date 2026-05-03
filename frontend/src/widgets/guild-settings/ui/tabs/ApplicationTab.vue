<script setup lang="ts">
import { Button, Card, CardContent, Label } from '@/shared/ui';
import type { GuildApplicationFormFieldDto } from '@/shared/api/guildsApi';
import type { ApplicationFormFieldType } from '@/features/guild-settings';

defineProps<{
  applicationShortUrl: string;
  guildPageShortUrl: string;
  /** Текст над формой заявки на публичной странице. */
  descriptionText: string;
  descriptionSaving: boolean;
  fields: GuildApplicationFormFieldDto[];
  saving: boolean;
  isRecruiting: boolean;
  togglingRecruiting: boolean;
  isSelectOrMultiselect: (t: ApplicationFormFieldType) => boolean;
}>();

const emit = defineEmits<{
  (e: 'update:descriptionText', value: string): void;
  (e: 'saveDescription'): void;
  (e: 'add'): void;
  (e: 'edit', index: number): void;
  (e: 'delete', index: number): void;
  (e: 'toggleRecruiting'): void;
}>();
</script>

<template>
  <Card v-show="true" class="mb-6 border-0 p-0 shadow-none">
    <CardContent class="space-y-6 px-2 pt-2">
      <p class="text-sm text-muted-foreground">
        Тут вы можете настроить форму для подачи заявки на вступление в гильдию. Для подачи заявки вам необходимо просто дать ссылку на форму. Короткая ссылка на форму заявки
        <a :href="applicationShortUrl" target="_blank" rel="noopener noreferrer" class="text-primary underline underline-offset-2 hover:no-underline">{{ applicationShortUrl }}</a>. Короткая ссылка на страницу гильдии
        <a :href="guildPageShortUrl" target="_blank" rel="noopener noreferrer" class="text-primary underline underline-offset-2 hover:no-underline">{{ guildPageShortUrl }}</a>.
      </p>

      <div class="space-y-2 border-b border-border pb-6">
        <Label for="application-form-description">Описание</Label>
        <textarea
          id="application-form-description"
          :value="descriptionText"
          rows="5"
          class="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring md:text-base"
          placeholder="Опишите информацию, которую необходимо знать пользователю при вступлении в гильдию"
          :disabled="descriptionSaving"
          @input="emit('update:descriptionText', ($event.target as HTMLTextAreaElement).value)"
        />
        <div class="flex flex-wrap gap-2 pt-1">
          <Button type="button" :disabled="descriptionSaving" @click="emit('saveDescription')">
            {{ descriptionSaving ? 'Сохранение…' : 'Сохранить описание' }}
          </Button>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-2">
        <h3 class="text-sm font-medium text-foreground underline decoration-border underline-offset-2">
          Дополнительные поля
        </h3>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          :disabled="saving"
          @click="emit('add')"
        >
          <span class="mr-1.5 text-base leading-none">+</span>
          Добавить дополнительное поле в форму заявки
        </Button>
      </div>

      <ul v-if="fields.length" class="space-y-2">
        <li
          v-for="(field, index) in fields"
          :key="field.id"
          class="flex items-center justify-between gap-2 rounded-md border border-border bg-muted/20 px-3 py-2 text-sm"
        >
          <div class="min-w-0 flex-1">
            <span class="font-medium text-foreground">
              {{ field.name }}
              <span v-if="field.required" aria-hidden="true">*</span>
            </span>
            <span
              v-if="isSelectOrMultiselect(field.type as ApplicationFormFieldType) && field.options?.length"
              class="ml-2 text-xs text-muted-foreground"
            >
              {{ field.type === 'select' ? 'Выбор' : 'Мультивыбор' }} ({{ field.options.length }})
            </span>
          </div>

          <div class="flex shrink-0 gap-1">
            <button
              type="button"
              class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
              aria-label="Редактировать поле"
              :disabled="saving"
              @click="emit('edit', index)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.85 2.83 0 1 1 4 4L7 17l-4 1 1-4Z" />
                <path d="m15 5 4 4" />
              </svg>
            </button>
            <button
              type="button"
              class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive disabled:opacity-50"
              aria-label="Удалить поле"
              :disabled="saving"
              @click="emit('delete', index)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18" />
                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                <line x1="10" x2="10" y1="11" y2="17" />
                <line x1="14" x2="14" y1="11" y2="17" />
              </svg>
            </button>
          </div>
        </li>
      </ul>
      <p v-else class="text-sm text-muted-foreground">
        Дополнительных полей пока нет. Нажмите кнопку выше, чтобы добавить.
      </p>

      <Button
        type="button"
        :variant="isRecruiting ? 'destructive' : 'success'"
        class="mt-4"
        :disabled="togglingRecruiting"
        @click="emit('toggleRecruiting')"
      >
        {{ togglingRecruiting ? 'Сохранение…' : isRecruiting ? 'Закрыть набор в гильдию' : 'Открыть набор в гильдию' }}
      </Button>
    </CardContent>
  </Card>
</template>

