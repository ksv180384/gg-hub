<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { accessApi, type PermissionGroupDto, PERMISSION_MANAGE_ROLES } from '@/shared/api/accessApi';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const canManage = () => auth.hasPermission(PERMISSION_MANAGE_ROLES);
const groups = ref<PermissionGroupDto[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const deleteDialogOpen = ref(false);
const groupToDelete = ref<PermissionGroupDto | null>(null);
const deletingId = ref<number | null>(null);

const GUILD_SCOPE = 'guild';

onMounted(async () => {
  try {
    groups.value = await accessApi.getPermissionGroups(GUILD_SCOPE);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
});

function openDeleteDialog(group: PermissionGroupDto) {
  groupToDelete.value = group;
  deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
  if (!deletingId.value) {
    groupToDelete.value = null;
    deleteDialogOpen.value = false;
  }
}

const canDeleteGroup = (group: PermissionGroupDto) =>
  !group.permissions?.length;

async function confirmDeleteGroup() {
  const group = groupToDelete.value;
  if (!group) return;
  deletingId.value = group.id;
  error.value = null;
  try {
    await accessApi.deletePermissionGroup(group.id);
    groups.value = groups.value.filter((g) => g.id !== group.id);
    groupToDelete.value = null;
    deleteDialogOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка удаления';
  } finally {
    deletingId.value = null;
  }
}
</script>

<template>
  <div class="container py-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Группы прав гильдии</h1>
      <div class="flex gap-2">
        <RouterLink to="/admin/guild-permissions">
          <Button variant="outline">Права гильдии</Button>
        </RouterLink>
        <RouterLink v-if="canManage()" to="/admin/guild-permission-groups/create">
          <Button>Добавить группу</Button>
        </RouterLink>
      </div>
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
          <div class="flex items-center gap-1 shrink-0">
            <RouterLink v-if="canManage()" :to="{ name: 'admin-guild-permission-groups-edit', params: { id: group.id } }">
              <Button variant="ghost" size="icon" class="h-8 w-8" aria-label="Редактировать" title="Редактировать">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                  <path d="m15 5 4 4" />
                </svg>
              </Button>
            </RouterLink>
            <Button
              v-if="canManage() && canDeleteGroup(group)"
              variant="ghost"
              size="icon"
              class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
              aria-label="Удалить"
              title="Удалить"
              @click="openDeleteDialog(group)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18" />
                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                <line x1="10" x2="10" y1="11" y2="17" />
                <line x1="14" x2="14" y1="11" y2="17" />
              </svg>
            </Button>
            <Button
              v-else-if="canManage() && !canDeleteGroup(group)"
              variant="ghost"
              size="icon"
              class="h-8 w-8 cursor-not-allowed opacity-50"
              aria-label="Удалить нельзя (есть права)"
              title="Удалить можно только группу без прав"
              disabled
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18" />
                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                <line x1="10" x2="10" y1="11" y2="17" />
                <line x1="14" x2="14" y1="11" y2="17" />
              </svg>
            </Button>
          </div>
        </CardHeader>
        <CardContent v-if="group.permissions?.length">
          <p class="text-xs text-muted-foreground">Права в группе:</p>
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
          <p class="text-sm text-muted-foreground">Нет прав в группе</p>
        </CardContent>
      </Card>
      <p v-if="!groups.length" class="text-sm text-muted-foreground">
        Нет групп. Создайте группу, чтобы добавлять в неё права гильдии.
      </p>
    </div>

    <ConfirmDialog
      :open="deleteDialogOpen"
      title="Удалить группу прав?"
      :description="groupToDelete ? `Группа «${groupToDelete.name}» будет удалена. Это действие нельзя отменить.` : ''"
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="deletingId === groupToDelete?.id"
      @update:open="(v) => { deleteDialogOpen = v; if (!v) closeDeleteDialog(); }"
      @confirm="confirmDeleteGroup"
    />
  </div>
</template>
