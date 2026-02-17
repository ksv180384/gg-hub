<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { accessApi, type PermissionGroupDto } from '@/shared/api/accessApi';

function slugFromName(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9.-]/g, '')
    .replace(/\.+/g, '.')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

const router = useRouter();
const groups = ref<PermissionGroupDto[]>([]);
const name = ref('');
const slug = ref('');
const description = ref('');
const permissionGroupId = ref<number | ''>('');
const submitting = ref(false);
const error = ref<string | null>(null);

const suggestedSlug = computed(() => slugFromName(name.value));
const effectiveSlug = computed(() => (slug.value.trim() || suggestedSlug.value));
const canSubmit = computed(
  () =>
    name.value.trim().length > 0 &&
    effectiveSlug.value.length > 0 &&
    permissionGroupId.value !== ''
);

onMounted(async () => {
  try {
    groups.value = await accessApi.getPermissionGroups();
    if (groups.value.length && permissionGroupId.value === '') {
      permissionGroupId.value = groups.value[0].id;
    }
  } catch {
    error.value = 'Не удалось загрузить группы прав';
  }
});

async function submit() {
  if (!canSubmit.value || permissionGroupId.value === '') return;
  submitting.value = true;
  error.value = null;
  try {
    await accessApi.createPermission({
      name: name.value.trim(),
      slug: effectiveSlug.value,
      description: description.value.trim() || undefined,
      permission_group_id: Number(permissionGroupId.value),
    });
    await router.push('/admin/permissions');
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
        <CardTitle>Добавить право</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div class="space-y-2">
          <Label for="name">Название</Label>
          <Input id="name" v-model="name" placeholder="Например: Управление играми" />
        </div>
        <div class="space-y-2">
          <Label for="slug">Слаг</Label>
          <Input id="slug" v-model="slug" placeholder="Например: games.manage" />
          <p class="text-xs text-muted-foreground">По слагу проверяется доступ. Если пусто — подставится из названия.</p>
        </div>
        <div class="space-y-2">
          <Label for="description">Описание</Label>
          <Input id="description" v-model="description" placeholder="Краткое описание права" />
        </div>
        <div class="space-y-2">
          <Label for="group">Группа прав</Label>
          <select
            id="group"
            v-model="permissionGroupId"
            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="">Выберите группу</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }} ({{ g.slug }})</option>
          </select>
        </div>
        <div class="flex gap-2 pt-2">
          <Button :disabled="!canSubmit || submitting" @click="submit">Создать</Button>
          <Button variant="outline" @click="router.push('/admin/permissions')">Отмена</Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
