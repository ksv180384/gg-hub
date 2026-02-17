<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
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
const route = useRoute();
const roleId = computed(() => Number(route.params.id));
const permissions = ref<PermissionDto[]>([]);
const name = ref('');
const slug = ref('');
const description = ref('');
const selectedPermissionIds = ref<number[]>([]);
const submitting = ref(false);
const loading = ref(true);
const error = ref<string | null>(null);

const suggestedSlug = computed(() => slugFromName(name.value));
const effectiveSlug = computed(() => (slug.value.trim() || suggestedSlug.value));
const canSubmit = computed(() => name.value.trim().length > 0 && effectiveSlug.value.length > 0);

function togglePermission(id: number) {
  const idx = selectedPermissionIds.value.indexOf(id);
  if (idx === -1) selectedPermissionIds.value = [...selectedPermissionIds.value, id];
  else selectedPermissionIds.value = selectedPermissionIds.value.filter((x) => x !== id);
}

async function loadRole() {
  const role = await accessApi.getRole(roleId.value);
  name.value = role.name;
  slug.value = role.slug;
  description.value = role.description ?? '';
  selectedPermissionIds.value = role.permissions?.map((p) => p.id) ?? [];
}

onMounted(async () => {
  try {
    const [perms, _] = await Promise.all([accessApi.getPermissions(), loadRole()]);
    permissions.value = perms;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
});

watch(roleId, () => loadRole(), { immediate: false });

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  error.value = null;
  try {
    await accessApi.updateRole(roleId.value, {
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
        <CardTitle>Редактировать роль</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div v-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
        <template v-else>
          <div class="space-y-2">
            <Label for="name">Название</Label>
            <Input id="name" v-model="name" />
          </div>
          <div class="space-y-2">
            <Label for="slug">Слаг</Label>
            <Input id="slug" v-model="slug" />
          </div>
          <div class="space-y-2">
            <Label for="description">Описание</Label>
            <Input id="description" v-model="description" />
          </div>
          <div class="space-y-2">
            <Label>Права</Label>
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
            </div>
          </div>
          <div class="flex gap-2 pt-2">
            <Button :disabled="!canSubmit || submitting" @click="submit">Сохранить</Button>
            <Button variant="outline" @click="router.push('/admin/roles')">Отмена</Button>
          </div>
        </template>
      </CardContent>
    </Card>
  </div>
</template>
