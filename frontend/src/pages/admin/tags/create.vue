<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { tagsApi } from '@/shared/api/tagsApi';

function slugFromName(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

const router = useRouter();
const name = ref('');
const slug = ref('');
const submitting = ref(false);
const error = ref<string | null>(null);

const suggestedSlug = computed(() => slugFromName(name.value));
const effectiveSlug = computed(() => (slug.value.trim() || suggestedSlug.value));
const canSubmit = computed(() => name.value.trim().length > 0);

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  error.value = null;
  try {
    await tagsApi.createTag({
      name: name.value.trim(),
      slug: effectiveSlug.value || undefined,
    });
    await router.push('/admin/tags');
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка сохранения';
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div class="container max-w-lg py-6">
    <Card>
      <CardHeader>
        <CardTitle>Добавить тег</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div class="space-y-2">
          <Label for="tag-name">Название *</Label>
          <Input id="tag-name" v-model="name" placeholder="Например: PvE" />
        </div>
        <div class="space-y-2">
          <Label for="tag-slug">Слаг</Label>
          <Input id="tag-slug" v-model="slug" placeholder="Например: pve" />
          <p class="text-xs text-muted-foreground">
            Уникальный идентификатор. Если пусто — подставится из названия.
          </p>
        </div>
        <div class="flex gap-2 pt-2">
          <Button :disabled="!canSubmit || submitting" @click="submit">Создать</Button>
          <Button variant="outline" @click="router.push('/admin/tags')">Отмена</Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
