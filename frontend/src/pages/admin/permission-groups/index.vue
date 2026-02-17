<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import { accessApi, type PermissionGroupDto } from '@/shared/api/accessApi';

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
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Категории прав</h1>
      <RouterLink to="/admin/permission-groups/create">
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
