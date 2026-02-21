<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';

const route = useRoute();
const router = useRouter();
const tag = ref<Tag | null>(null);
const name = ref('');
const slug = ref('');
const isHidden = ref(false);
const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

const tagId = computed(() => Number(route.params.id));
const canSubmit = computed(() => name.value.trim().length > 0);

onMounted(async () => {
  try {
    const list = await tagsApi.getTags(true);
    const t = list.find((x) => x.id === tagId.value);
    if (!t) {
      error.value = 'Тег не найден';
      return;
    }
    tag.value = t;
    name.value = t.name;
    slug.value = t.slug;
    isHidden.value = t.is_hidden;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
});

watch(tag, (t) => {
  if (t) {
    name.value = t.name;
    slug.value = t.slug;
    isHidden.value = t.is_hidden;
  }
});

async function submit() {
  if (!canSubmit.value || !tag.value) return;
  submitting.value = true;
  error.value = null;
  try {
    await tagsApi.updateTag(tag.value.id, {
      name: name.value.trim(),
      slug: slug.value.trim() || undefined,
      is_hidden: isHidden.value,
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
    <Card v-if="tag">
      <CardHeader>
        <CardTitle>Редактировать тег</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div class="space-y-2">
          <Label for="tag-name">Название *</Label>
          <Input id="tag-name" v-model="name" placeholder="Название тега" />
        </div>
        <div class="space-y-2">
          <Label for="tag-slug">Слаг</Label>
          <Input id="tag-slug" v-model="slug" placeholder="slug" />
        </div>
        <div class="flex items-center gap-2">
          <input
            id="tag-hidden"
            v-model="isHidden"
            type="checkbox"
            class="h-4 w-4 rounded border-input"
          />
          <Label for="tag-hidden" class="cursor-pointer">Скрытый (не показывать в выборе при добавлении к гильдии/персонажу)</Label>
        </div>
        <div class="flex gap-2 pt-2">
          <Button :disabled="!canSubmit || submitting" @click="submit">Сохранить</Button>
          <Button variant="outline" @click="router.push('/admin/tags')">Отмена</Button>
        </div>
      </CardContent>
    </Card>
    <div v-else-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <div v-else class="text-sm text-destructive">Тег не найден.</div>
  </div>
</template>
