<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { accessApi, type PermissionGroupDto, PERMISSION_MANAGE_ROLES } from '@/shared/api/accessApi';

const auth = useAuthStore();
const canManageRoles = computed(() => auth.hasPermission(PERMISSION_MANAGE_ROLES));
const groups = ref<PermissionGroupDto[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    groups.value = await accessApi.getPermissionGroups('site');
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="container py-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Категории прав</h1>
      <RouterLink v-if="canManageRoles" to="/admin/permission-groups/create">
        <Button>Добавить категорию</Button>
      </RouterLink>
    </div>
    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <div v-else-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <div v-else class="space-y-4">
      <Card v-for="group in groups" :key="group.id">
        <CardHeader class="flex flex-row items-center justify-between">
          <div>
            <CardTitle class="text-base">{{ group.name }}</CardTitle>
            <p class="mt-1 text-sm text-muted-foreground">Слаг: {{ group.slug }}</p>
          </div>
          <RouterLink v-if="canManageRoles" :to="{ name: 'admin-permission-groups-edit', params: { id: group.id } }">
            <Button variant="ghost" size="icon" class="h-8 w-8" aria-label="Редактировать" title="Редактировать">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                <path d="m15 5 4 4" />
              </svg>
            </Button>
          </RouterLink>
        </CardHeader>
        <CardContent v-if="group.permissions?.length">
          <p class="text-xs text-muted-foreground">Права в категории:</p>
          <div class="mt-1 flex flex-wrap gap-1">
            <span
              v-for="p in group.permissions"
              :key="p.id"
              class="rounded bg-muted px-2 py-0.5 text-xs"
            >
              {{ p.slug }}
            </span>
          </div>
        </CardContent>
        <CardContent v-else>
          <p class="text-sm text-muted-foreground">Нет прав в категории</p>
        </CardContent>
      </Card>
      <p v-if="!groups.length" class="text-sm text-muted-foreground">
        Нет категорий. Создайте категорию, чтобы добавлять в неё права.
      </p>
    </div>
  </div>
</template>
