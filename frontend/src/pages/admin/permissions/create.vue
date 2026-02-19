<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
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
const permissionGroupValue = computed({
  get: () => (permissionGroupId.value === '' ? '' : String(permissionGroupId.value)),
  set: (v: string) => {
    permissionGroupId.value = v === '' ? '' : Number(v);
  },
});
const canSubmit = computed(
  () =>
    name.value.trim().length > 0 &&
    permissionGroupId.value !== ''
);

onMounted(async () => {
  try {
    groups.value = await accessApi.getPermissionGroups();
    const first = groups.value[0];
    if (first && permissionGroupId.value === '') {
      permissionGroupId.value = first.id;
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
          <p v-if="!groups.length" class="text-xs text-muted-foreground">
            Сначала создайте категорию прав на странице «Категории прав».
          </p>
        </div>
        <div class="flex gap-2 pt-2">
          <Button :disabled="!canSubmit || submitting" @click="submit">Создать</Button>
          <Button variant="outline" @click="router.push('/admin/permissions')">Отмена</Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
