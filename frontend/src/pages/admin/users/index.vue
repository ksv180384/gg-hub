<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge } from '@/shared/ui';
import { accessApi, type AdminUserDto } from '@/shared/api/accessApi';

const users = ref<AdminUserDto[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    users.value = await accessApi.getUsers();
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
      <h1 class="text-2xl font-semibold">Пользователи</h1>
    </div>
    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <div v-else-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <div v-else class="space-y-4">
      <Card v-for="u in users" :key="u.id">
        <CardHeader class="flex flex-row items-center justify-between">
          <div>
            <CardTitle class="text-base">{{ u.name }}</CardTitle>
            <p class="mt-1 text-sm text-muted-foreground">{{ u.email }}</p>
          </div>
          <div class="flex items-center gap-2">
            <Badge v-if="u.banned_at" variant="destructive">Заблокирован</Badge>
            <RouterLink :to="`/admin/users/${u.id}`">
              <Button variant="outline" size="sm">Карточка</Button>
            </RouterLink>
          </div>
        </CardHeader>
        <CardContent v-if="u.roles?.length || u.permissions?.length">
          <div v-if="u.roles?.length" class="mb-2">
            <span class="text-xs text-muted-foreground">Роли:</span>
            <span class="ml-1 text-sm">{{ u.roles.map((r) => r.name).join(', ') }}</span>
          </div>
          <div v-if="u.permissions?.length">
            <span class="text-xs text-muted-foreground">Права:</span>
            <span class="ml-1 text-sm">{{ u.permissions.join(', ') }}</span>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
