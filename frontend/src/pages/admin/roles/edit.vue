<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label, TooltipProvider, Tooltip } from '@/shared/ui';
import { accessApi, type PermissionGroupDto } from '@/shared/api/accessApi';

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
const groups = ref<PermissionGroupDto[]>([]);
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
    const [groupsData, _] = await Promise.all([accessApi.getPermissionGroups(), loadRole()]);
    groups.value = groupsData;
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
  <div class="container max-w-5xl py-6">
    <div class="flex flex-col gap-6 sm:flex-row">
      <!-- Левая колонка: форма (sticky) -->
      <div class="sm:w-80 sm:shrink-0 sm:sticky sm:top-6 sm:self-start">
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
              <div class="flex gap-2 pt-2">
                <Button :disabled="!canSubmit || submitting" @click="submit">Сохранить</Button>
                <Button variant="outline" @click="router.push('/admin/roles')">Отмена</Button>
              </div>
            </template>
          </CardContent>
        </Card>
      </div>
      <!-- Правая колонка: права -->
      <div class="sm:min-w-0 sm:flex-1">
      <Card>
        <CardHeader>
          <CardTitle class="text-base">Права</CardTitle>
          <p class="text-xs text-muted-foreground">Отметьте права, которые получают пользователи с этой ролью.</p>
        </CardHeader>
        <CardContent>
          <TooltipProvider>
            <div class="max-h-[calc(100vh-12rem)] space-y-4 overflow-y-auto rounded-md border p-3">
              <div v-for="group in groups" :key="group.id" class="space-y-2">
                <div class="text-xs font-medium text-muted-foreground">{{ group.name }}</div>
                <div class="space-y-1.5 pl-1">
                  <Tooltip
                    v-for="p in (group.permissions ?? [])"
                    :key="p.id"
                    :content="p.description ?? ''"
                  >
                    <label
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
                  </Tooltip>
                </div>
              </div>
            </div>
          </TooltipProvider>
        </CardContent>
      </Card>
      </div>
    </div>
  </div>
</template>
