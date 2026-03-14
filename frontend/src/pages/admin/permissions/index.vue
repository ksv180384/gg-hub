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
    <div v-else class="space-y-4">
      <Card v-for="group in groups" :key="group.id">
        <CardHeader class="pb-2">
          <CardTitle class="text-base">{{ group.name }} ({{ group.slug }})</CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <div v-if="group.permissions?.length" class="flex flex-col gap-1.5">
            <div
              v-for="p in group.permissions"
              :key="p.id"
              class="flex items-center gap-1.5 rounded border px-2 py-1 text-sm"
            >
              <span class="font-medium">{{ p.name }}</span>
              <span class="text-muted-foreground" :title="p.description ?? undefined">{{ p.slug }}</span>
              <RouterLink v-if="canManageRoles" :to="{ name: 'admin-permissions-edit', params: { id: p.id } }">
                <Button variant="ghost" size="icon" class="h-6 w-6 shrink-0" aria-label="Редактировать" title="Редактировать">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                    <path d="m15 5 4 4" />
                  </svg>
                </Button>
              </RouterLink>
            </div>
          </div>
          <p v-else class="text-sm text-muted-foreground">Нет прав в группе</p>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
