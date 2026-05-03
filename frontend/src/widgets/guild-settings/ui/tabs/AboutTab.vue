<script setup lang="ts">
import { Button, Card, CardContent, Label } from '@/shared/ui';
import RichTextEditor from '@/shared/ui/rich-text-editor/RichTextEditor.vue';

defineProps<{
  canEdit: boolean;
  isOwner: boolean;
  saving: boolean;
  modelValue: string;
  previewMode: boolean;
  readOnlyHtml: string | null;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'update:previewMode', value: boolean): void;
  (e: 'save'): void;
}>();
</script>

<template>
  <Card v-show="true" class="mb-6 border-0 p-0 shadow-none">
    <CardContent class="space-y-6 px-2">
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
          <Label for="about-text">Текст «О гильдии»</Label>
          <RichTextEditor
            id="about-text"
            :model-value="modelValue"
            placeholder="Расскажите о гильдии, целях и правилах…"
            :disabled="saving || !isOwner"
            @update:model-value="emit('update:modelValue', $event)"
          />
        </div>

        <div
          v-show="previewMode"
          class="min-h-[200px] rounded-md border border-input bg-muted/30 px-3 py-3 text-sm"
        >
          <div
            v-if="modelValue"
            class="prose prose-sm max-w-none text-muted-foreground dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:decoration-blue-600/40 [&_a]:underline-offset-2 hover:[&_a]:text-blue-700 dark:[&_a]:text-blue-400 dark:hover:[&_a]:text-blue-300 dark:[&_a]:decoration-blue-400/50 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-foreground [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_ol]:space-y-1 [&_li]:my-0.5"
            v-html="modelValue"
          />
          <p v-else class="text-muted-foreground">
            Нет текста. Переключитесь в режим редактирования и добавьте описание.
          </p>
        </div>

        <div v-show="!previewMode" class="flex flex-wrap gap-2 pt-2">
          <Button :disabled="saving || !isOwner" @click="emit('save')">
            {{ saving ? 'Сохранение…' : 'Сохранить' }}
          </Button>
        </div>
      </template>

      <template v-else>
        <div
          v-if="readOnlyHtml"
          class="prose prose-sm max-w-none text-muted-foreground dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:decoration-blue-600/40 [&_a]:underline-offset-2 hover:[&_a]:text-blue-700 dark:[&_a]:text-blue-400 dark:hover:[&_a]:text-blue-300 dark:[&_a]:decoration-blue-400/50 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-foreground [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_ol]:space-y-1 [&_li]:my-0.5"
          v-html="readOnlyHtml"
        />
        <p v-else class="text-sm text-muted-foreground">—</p>
      </template>
    </CardContent>
  </Card>
</template>

