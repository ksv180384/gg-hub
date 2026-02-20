<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { accessApi, type PermissionGroupDto, PERMISSION_MANAGE_ROLES } from '@/shared/api/accessApi';

const auth = useAuthStore();

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
const route = useRoute();
const id = computed(() => Number(route.params.id));

const group = ref<PermissionGroupDto | null>(null);
const loading = ref(true);
const name = ref('');
const slug = ref('');
const submitting = ref(false);
const error = ref<string | null>(null);

const suggestedSlug = computed(() => slugFromName(name.value));
const effectiveSlug = computed(() => (slug.value.trim() || suggestedSlug.value));
const canSubmit = computed(() => name.value.trim().length > 0);

async function load() {
  if (!id.value || Number.isNaN(id.value)) {
    router.replace('/admin/permission-groups');
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    group.value = await accessApi.getPermissionGroup(id.value);
    name.value = group.value.name;
    slug.value = group.value.slug;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!canSubmit.value || !group.value) return;
  submitting.value = true;
  error.value = null;
  try {
    await accessApi.updatePermissionGroup(group.value.id, {
      name: name.value.trim(),
      slug: effectiveSlug.value,
    });
    await router.push('/admin/permission-groups');
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка сохранения';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  if (!auth.hasPermission(PERMISSION_MANAGE_ROLES)) {
    router.replace('/admin/permission-groups');
    return;
  }
  load();
});

watch(id, (newId) => {
  if (newId && !Number.isNaN(newId)) load();
});
</script>

<template>
  <div class="container max-w-lg py-6">
    <Card>
      <CardHeader>
        <CardTitle>Редактировать категорию прав</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div v-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
        <template v-else-if="group">
          <div class="space-y-2">
            <Label for="name">Название *</Label>
            <Input id="name" v-model="name" placeholder="Например: Администрирование" />
          </div>
          <div class="space-y-2">
            <Label for="slug">Слаг</Label>
            <Input id="slug" v-model="slug" placeholder="Например: admin" />
            <p class="text-xs text-muted-foreground">
              Уникальный идентификатор категории. Если пусто — подставится из названия.
            </p>
          </div>
          <div class="flex gap-2 pt-2">
            <Button :disabled="!canSubmit || submitting" @click="submit">Сохранить</Button>
            <Button variant="outline" @click="router.push('/admin/permission-groups')">Отмена</Button>
          </div>
        </template>
      </CardContent>
    </Card>
  </div>
</template>
