<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Label,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { accessApi, type PermissionGroupDto, type PermissionDto, PERMISSION_MANAGE_ROLES } from '@/shared/api/accessApi';

const auth = useAuthStore();

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
const route = useRoute();
const id = computed(() => Number(route.params.id));

const permission = ref<PermissionDto | null>(null);
const groups = ref<PermissionGroupDto[]>([]);
const loading = ref(true);
const name = ref('');
const slug = ref('');
const description = ref('');
const permissionGroupId = ref<number | ''>('');
const submitting = ref(false);
const error = ref<string | null>(null);

const suggestedSlug = computed(() => slugFromName(name.value));
const effectiveSlug = computed(() => (slug.value.trim() || suggestedSlug.value));
const permissionGroupValue = computed({
  get: () => (permissionGroupId.value === '' ? '' : String(permissionGroupId.value)),
  set: (v: string) => {
    permissionGroupId.value = v === '' ? '' : Number(v);
  },
});
const canSubmit = computed(
  () => name.value.trim().length > 0 && permissionGroupId.value !== ''
);

async function load() {
  if (!id.value || Number.isNaN(id.value)) {
    router.replace('/admin/permissions');
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    const [perm, groupsList] = await Promise.all([
      accessApi.getPermission(id.value),
      accessApi.getPermissionGroups(),
    ]);
    permission.value = perm;
    groups.value = groupsList;
    name.value = perm.name;
    slug.value = perm.slug;
    description.value = perm.description ?? '';
    permissionGroupId.value = perm.permission_group_id;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!canSubmit.value || !permission.value || permissionGroupId.value === '') return;
  submitting.value = true;
  error.value = null;
  try {
    await accessApi.updatePermission(permission.value.id, {
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

onMounted(() => {
  if (!auth.hasPermission(PERMISSION_MANAGE_ROLES)) {
    router.replace('/admin/permissions');
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
        <CardTitle>Редактировать право</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div v-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
        <template v-else-if="permission">
          <div class="space-y-2">
            <Label for="name">Название *</Label>
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
            <Label>Группа прав</Label>
            <SelectRoot v-model="permissionGroupValue" :disabled="!groups.length">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="Выберите группу" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="g in groups"
                  :key="g.id"
                  :value="String(g.id)"
                >
                  {{ g.name }} ({{ g.slug }})
                </SelectItem>
              </SelectContent>
            </SelectRoot>
          </div>
          <div class="flex gap-2 pt-2">
            <Button :disabled="!canSubmit || submitting" @click="submit">Сохранить</Button>
            <Button variant="outline" @click="router.push('/admin/permissions')">Отмена</Button>
          </div>
        </template>
      </CardContent>
    </Card>
  </div>
</template>
