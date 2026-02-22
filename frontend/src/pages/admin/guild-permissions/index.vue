<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import {
  accessApi,
  type PermissionGroupDto,
  PERMISSION_GUILD_ADD,
  PERMISSION_GUILD_EDIT,
  PERMISSION_GUILD_DELETE,
} from '@/shared/api/accessApi';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const canAdd = () => auth.hasPermission(PERMISSION_GUILD_ADD);
const canEdit = () => auth.hasPermission(PERMISSION_GUILD_EDIT);
const canDelete = () => auth.hasPermission(PERMISSION_GUILD_DELETE);
const groups = ref<PermissionGroupDto[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const deleteDialogOpen = ref(false);
const permissionToDelete = ref<{ id: number; name: string } | null>(null);
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

function openDeleteDialog(p: { id: number; name: string }) {
  permissionToDelete.value = p;
  deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
  if (!deletingId.value) {
    permissionToDelete.value = null;
    deleteDialogOpen.value = false;
  }
}

async function confirmDeletePermission() {
  const p = permissionToDelete.value;
  if (!p) return;
  deletingId.value = p.id;
  error.value = null;
  try {
    await accessApi.deletePermission(p.id);
    for (const g of groups.value) {
      if (g.permissions) {
        g.permissions = g.permissions.filter((perm) => perm.id !== p.id);
      }
    }
    permissionToDelete.value = null;
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
    <div class="mb-6 flex items-center justify-between gap-4 flex-wrap">
      <h1 class="text-2xl font-semibold">Права гильдии</h1>
      <div class="flex gap-2">
        <RouterLink to="/admin/guild-permission-groups">
          <Button variant="outline">Группы прав гильдии</Button>
        </RouterLink>
        <RouterLink v-if="canAdd()" to="/admin/guild-permissions/create">
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
              class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
            >
              <div>
                <span class="font-medium">{{ p.name }}</span>
                <span class="ml-2 text-muted-foreground">{{ p.slug }}</span>
                <p v-if="p.description" class="mt-1 text-xs text-muted-foreground">{{ p.description }}</p>
              </div>
              <div class="flex gap-1 shrink-0">
                <RouterLink v-if="canEdit()" :to="{ name: 'admin-guild-permissions-edit', params: { id: p.id } }">
                  <Button variant="ghost" size="icon" class="h-8 w-8" aria-label="Редактировать" title="Редактировать">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                      <path d="m15 5 4 4" />
                    </svg>
                  </Button>
                </RouterLink>
                <Button
                  v-if="canDelete()"
                  variant="ghost"
                  size="icon"
                  class="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                  aria-label="Удалить"
                  title="Удалить"
                  @click="openDeleteDialog({ id: p.id, name: p.name })"
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
            </li>
          </ul>
          <p v-else class="text-sm text-muted-foreground">Нет прав в группе</p>
        </CardContent>
      </Card>
      <p v-if="!groups.length" class="text-sm text-muted-foreground">
        Нет групп прав гильдии. Сначала создайте группу на странице «Группы прав гильдии».
      </p>
    </div>

    <ConfirmDialog
      :open="deleteDialogOpen"
      title="Удалить право?"
      :description="permissionToDelete ? `Право «${permissionToDelete.name}» будет удалено. Это действие нельзя отменить.` : ''"
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="deletingId === permissionToDelete?.id"
      @update:open="(v) => { deleteDialogOpen = v; if (!v) closeDeleteDialog(); }"
      @confirm="confirmDeletePermission"
    />
  </div>
</template>
