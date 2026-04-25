<script setup lang="ts">
import { Button, Card, CardContent, Label } from '@/shared/ui';

defineProps<{
  canEdit: boolean;
  isOwner: boolean;
  saving: boolean;
  modelValue: string;
  previewMode: boolean;
  readOnlyText: string | null;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'update:previewMode', value: boolean): void;
  (e: 'save'): void;
}>();
</script>

<template>
  <Card v-show="true" class="mb-6 border-0 p-0 shadow-none">
    <CardContent class="space-y-4 px-2">
      <template v-if="canEdit">
        <div class="flex flex-wrap items-center gap-2 border-b border-border pb-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :class="{ 'bg-muted': !previewMode }"
            @click="emit('update:previewMode', false)"
          >
            Редактирование
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :class="{ 'bg-muted': previewMode }"
            @click="emit('update:previewMode', true)"
          >
            Предпросмотр
          </Button>
        </div>

        <div v-show="!previewMode" class="space-y-2">
          <Label for="charter-text">Текст устава</Label>
          <textarea
            id="charter-text"
            :value="modelValue"
            rows="12"
            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            placeholder="Устав гильдии…"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
          />
        </div>

        <div
          v-show="previewMode"
          class="min-h-[200px] rounded-md border border-input bg-muted/30 px-3 py-3 text-sm"
        >
          <p v-if="modelValue" class="whitespace-pre-wrap text-muted-foreground">
            {{ modelValue }}
          </p>
          <p v-else class="text-muted-foreground">
            Нет текста. Переключитесь в режим редактирования и добавьте устав.
          </p>
        </div>

        <div v-show="!previewMode" class="flex flex-wrap gap-2 pt-2">
          <Button :disabled="saving || !isOwner" @click="emit('save')">
            {{ saving ? 'Сохранение…' : 'Сохранить' }}
          </Button>
        </div>
      </template>

      <p v-else class="whitespace-pre-wrap text-sm text-muted-foreground">
        {{ readOnlyText || '—' }}
      </p>
    </CardContent>
  </Card>
</template>

