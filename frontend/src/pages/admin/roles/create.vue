<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label } from '@/shared/ui';
import { accessApi, type PermissionDto } from '@/shared/api/accessApi';

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
const permissions = ref<PermissionDto[]>([]);
const name = ref('');
const slug = ref('');
const description = ref('');
const selectedPermissionIds = ref<number[]>([]);
const submitting = ref(false);
const error = ref<string | null>(null);

const suggestedSlug = computed(() => slugFromName(name.value));
const effectiveSlug = computed(() => (slug.value.trim() || suggestedSlug.value));
const canSubmit = computed(() => name.value.trim().length > 0 && effectiveSlug.value.length > 0);

function togglePermission(id: number) {
  const idx = selectedPermissionIds.value.indexOf(id);
  if (idx === -1) selectedPermissionIds.value = [...selectedPermissionIds.value, id];
  else selectedPermissionIds.value = selectedPermissionIds.value.filter((x) => x !== id);
}

onMounted(async () => {
  try {
    permissions.value = await accessApi.getPermissions();
  } catch {
    error.value = 'Не удалось загрузить список прав';
  }
});

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  error.value = null;
  try {
    await accessApi.createRole({
      name: name.value.trim(),
      slug: effectiveSlug.value,
      description: description.value.trim() || undefined,
      permission_ids: selectedPermissionIds.value,
    });
    await router.push('/admin/roles');
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
        <CardTitle>Добавить роль</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div class="space-y-2">
          <Label for="name">Название</Label>
          <Input id="name" v-model="name" placeholder="Например: Модератор" />
        </div>
        <div class="space-y-2">
          <Label for="slug">Слаг</Label>
          <Input id="slug" v-model="slug" placeholder="Например: moderator" />
          <p class="text-xs text-muted-foreground">По слагу определяется роль. Если пусто — подставится из названия.</p>
        </div>
        <div class="space-y-2">
          <Label for="description">Описание</Label>
          <Input id="description" v-model="description" placeholder="Краткое описание роли" />
        </div>
        <div class="space-y-2">
          <Label>Права</Label>
          <p class="text-xs text-muted-foreground">Отметьте права, которые получают пользователи с этой ролью.</p>
          <div class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-md border p-3">
            <label
              v-for="p in permissions"
              :key="p.id"
              class="flex cursor-pointer items-center gap-2 text-sm"
            >
              <input
                type="checkbox"
                :checked="selectedPermissionIds.includes(p.id)"
                @change="togglePermission(p.id)"
              />
              <span>{{ p.name }}</span>
              <span class="text-muted-foreground">({{ p.slug }})</span>
            </label>
            <p v-if="!permissions.length" class="text-sm text-muted-foreground">Нет доступных прав. Создайте права в разделе «Права пользователей».</p>
          </div>
        </div>
        <div class="flex gap-2 pt-2">
          <Button :disabled="!canSubmit || submitting" @click="submit">Создать</Button>
          <Button variant="outline" @click="router.push('/admin/roles')">Отмена</Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
