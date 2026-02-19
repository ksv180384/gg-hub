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
    groups.value = await accessApi.getPermissionGroups();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="container py-6">
    <div class="mb-6 flex items-center justify-between gap-4 flex-wrap">
      <h1 class="text-2xl font-semibold">Права пользователей</h1>
      <div class="flex gap-2">
        <RouterLink to="/admin/permission-groups">
          <Button variant="outline">Категории прав</Button>
        </RouterLink>
        <RouterLink v-if="canManageRoles" to="/admin/permissions/create">
          <Button>Добавить право</Button>
        </RouterLink>
      </div>
    </div>
    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <div v-else-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <div v-else class="space-y-6">
      <Card v-for="group in groups" :key="group.id">
        <CardHeader>
          <CardTitle class="text-base">{{ group.name }} ({{ group.slug }})</CardTitle>
        </CardHeader>
        <CardContent>
          <ul v-if="group.permissions?.length" class="space-y-2">
            <li
              v-for="p in group.permissions"
              :key="p.id"
              class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
            >
              <div>
                <span class="font-medium">{{ p.name }}</span>
                <span class="ml-2 text-muted-foreground">{{ p.slug }}</span>
                <p v-if="p.description" class="mt-1 text-xs text-muted-foreground">{{ p.description }}</p>
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">Нет прав в группе</p>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
