<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Input } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { guildsApi, type GuildRole, type Guild } from '@/shared/api/guildsApi';
import type { PermissionGroupDto } from '@/shared/api/accessApi';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));

const guild = ref<Guild | null>(null);
const roles = ref<GuildRole[]>([]);
const myPermissionSlugs = ref<string[]>([]);
const permissionGroups = ref<PermissionGroupDto[]>([]);
const selectedRole = ref<GuildRole | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const addingRole = ref(false);
const newRoleName = ref('');
const creating = ref(false);
const savingPermissionIds = ref<Set<number>>(new Set());

const deleteDialogOpen = ref(false);
const roleToDelete = ref<GuildRole | null>(null);
const deletingRoleId = ref<number | null>(null);

/** Роль «Лидер»: все права включены и недоступны для изменения. */
const GUILD_LEADER_ROLE_SLUG = 'leader';
/** Роли, которые нельзя удалять. */
const PROTECTED_ROLE_SLUGS = ['leader', 'novice'];

const canAddRole = computed(() => myPermissionSlugs.value.includes('dobavliat-rol'));
const canEditPermissions = computed(() => myPermissionSlugs.value.includes('izmeniat-prava-roli'));
const canDeleteRole = computed(() => myPermissionSlugs.value.includes('udaliat-rol'));

function canDeleteRoleFor(role: GuildRole): boolean {
  return canDeleteRole.value && !PROTECTED_ROLE_SLUGS.includes(role.slug);
}

/** Текущий список id прав выбранной роли (для отображения галочек). */
const selectedPermissionIds = computed(() => {
  const role = selectedRole.value;
  if (!role?.permissions) return [];
  return role.permissions.map((p) => p.id);
});

const isLeaderRole = computed(() => selectedRole.value?.slug === GUILD_LEADER_ROLE_SLUG);

/** Галочки прав доступны только при праве izmeniat-prava-roli (плюс для Лидера всегда disabled). */
const canTogglePermissions = computed(() => canEditPermissions.value && !isLeaderRole.value);

function isPermissionChecked(permissionId: number): boolean {
  if (isLeaderRole.value) return true;
  return selectedPermissionIds.value.includes(permissionId);
}

async function loadData() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  loading.value = true;
  error.value = null;
  try {
    const [guildData, rolesResult, groupsData] = await Promise.all([
      guildsApi.getGuildForSettings(guildId.value),
      guildsApi.getGuildRoles(guildId.value),
      guildsApi.getGuildPermissionGroups(guildId.value),
    ]);
    guild.value = guildData;
    roles.value = rolesResult.roles;
    myPermissionSlugs.value = rolesResult.myPermissionSlugs;
    permissionGroups.value = groupsData;
    if (!selectedRole.value && rolesResult.roles.length > 0) {
      selectedRole.value = rolesResult.roles[0];
    }
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 403) {
      router.replace({ name: 'guild-settings', params: { id: String(guildId.value) } });
      return;
    }
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
}

function selectRole(role: GuildRole) {
  selectedRole.value = role;
}

function startAddRole() {
  addingRole.value = true;
  newRoleName.value = '';
}

function cancelAddRole() {
  addingRole.value = false;
  newRoleName.value = '';
}

async function createRole() {
  const name = newRoleName.value.trim();
  if (!name || creating.value) return;
  creating.value = true;
  error.value = null;
  try {
    const role = await guildsApi.createGuildRole(guildId.value, { name });
    roles.value = [...roles.value, role];
    selectedRole.value = role;
    addingRole.value = false;
    newRoleName.value = '';
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка создания роли';
  } finally {
    creating.value = false;
  }
}

/** Переключение права с немедленной отправкой на сервер. Для роли «Лидер» или без права не вызывается. */
async function togglePermission(permissionId: number) {
  const role = selectedRole.value;
  if (!role || role.slug === GUILD_LEADER_ROLE_SLUG || !canEditPermissions.value) return;
  const current = selectedPermissionIds.value;
  const next = current.includes(permissionId)
    ? current.filter((id) => id !== permissionId)
    : [...current, permissionId];
  savingPermissionIds.value.add(permissionId);
  try {
    const updated = await guildsApi.updateGuildRolePermissions(guildId.value, role.id, next);
    const idx = roles.value.findIndex((r) => r.id === role.id);
    if (idx >= 0) roles.value[idx] = updated;
    selectedRole.value = updated;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка сохранения прав';
  } finally {
    savingPermissionIds.value.delete(permissionId);
  }
}

function openDeleteDialog(role: GuildRole) {
  if (!canDeleteRoleFor(role) || deleteDialogOpen.value) return;
  roleToDelete.value = role;
  deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
  if (!deletingRoleId.value) {
    roleToDelete.value = null;
    deleteDialogOpen.value = false;
  }
}

async function confirmDeleteRole() {
  const role = roleToDelete.value;
  if (!role) return;
  deletingRoleId.value = role.id;
  error.value = null;
  try {
    await guildsApi.deleteGuildRole(guildId.value, role.id);
    roles.value = roles.value.filter((r) => r.id !== role.id);
    if (selectedRole.value?.id === role.id) {
      selectedRole.value = roles.value[0] ?? null;
    }
    roleToDelete.value = null;
    deleteDialogOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось удалить роль';
  } finally {
    deletingRoleId.value = null;
  }
}

onMounted(loadData);
watch(guildId, () => loadData());
</script>

<template>
  <div class="container py-6">
    <p v-if="error" class="mb-4 text-sm text-destructive">{{ error }}</p>

    <div v-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <div v-else class="flex flex-col gap-6 lg:flex-row">
      <!-- Левая колонка: список ролей -->
      <div class="w-full shrink-0 lg:w-72">
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Список ролей</CardTitle>
            <Button
              v-if="canAddRole && !addingRole"
              size="sm"
              class="mt-2 w-full justify-center"
              @click="startAddRole"
            >
              + Добавить
            </Button>
            <div v-else class="mt-2 space-y-2">
              <Input
                v-model="newRoleName"
                placeholder="Название роли"
                class="h-8 text-sm"
                :disabled="creating"
                @keydown.enter.prevent="createRole"
              />
              <div class="flex gap-1">
                <Button
                  size="sm"
                  class="flex-1"
                  :disabled="!newRoleName.trim() || creating"
                  @click="createRole"
                >
                  {{ creating ? '…' : 'Создать' }}
                </Button>
                <Button size="sm" variant="outline" :disabled="creating" @click="cancelAddRole">
                  Отмена
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent class="p-0">
            <ul class="max-h-[60vh] overflow-y-auto">
              <li
                v-for="role in roles"
                :key="role.id"
                class="flex items-center justify-between gap-2 border-b border-border px-4 py-3 last:border-b-0 transition-colors hover:bg-muted/50"
                :class="{ 'bg-muted': selectedRole?.id === role.id, 'cursor-pointer': true }"
                @click="selectRole(role)"
              >
                <span class="min-w-0 flex-1 font-medium">{{ role.name }}</span>
                <Button
                  v-if="canDeleteRoleFor(role)"
                  variant="ghost"
                  size="sm"
                  class="shrink-0 text-destructive hover:text-destructive"
                  :disabled="deletingRoleId === role.id"
                  @click.stop="openDeleteDialog(role)"
                >
                  Удалить
                </Button>
              </li>
              <li v-if="!roles.length" class="px-4 py-6 text-center text-sm text-muted-foreground">
                Нет ролей. Нажмите «+ Добавить».
              </li>
            </ul>
          </CardContent>
        </Card>
      </div>

      <!-- Правая колонка: права выбранной роли -->
      <div class="min-w-0 flex-1">
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Список прав группы пользователей</CardTitle>
            <p v-if="selectedRole" class="text-xs text-muted-foreground">
              <template v-if="isLeaderRole">
                У роли «Лидер» все права включены и не изменяются.
              </template>
              <template v-else>
                Роль «{{ selectedRole.name }}». Включение или отключение права сохраняется сразу.
              </template>
            </p>
          </CardHeader>
          <CardContent>
            <div v-if="!selectedRole" class="py-8 text-center text-sm text-muted-foreground">
              Выберите роль слева или создайте новую.
            </div>
            <div v-else class="max-h-[calc(100vh-14rem)] space-y-4 overflow-y-auto">
              <div v-for="group in permissionGroups" :key="group.id" class="space-y-2">
                <div class="text-xs font-medium text-muted-foreground">{{ group.name }}</div>
                <div class="space-y-1.5 pl-1">
                  <label
                    v-for="p in (group.permissions ?? [])"
                    :key="p.id"
                    class="flex items-center gap-2 text-sm"
                    :class="{ 'cursor-pointer': canTogglePermissions, 'cursor-not-allowed opacity-80': !canTogglePermissions }"
                  >
                    <input
                      type="checkbox"
                      :checked="isPermissionChecked(p.id)"
                      :disabled="!canTogglePermissions || savingPermissionIds.has(p.id)"
                      @change="togglePermission(p.id)"
                    />
                    <span>{{ p.name }}</span>
                    <span v-if="savingPermissionIds.has(p.id)" class="text-xs text-muted-foreground">…</span>
                  </label>
                </div>
              </div>
              <p v-if="permissionGroups.length === 0" class="text-sm text-muted-foreground">
                Нет групп прав гильдии. Добавьте их в админке в разделе «Права гильдии».
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <ConfirmDialog
      :open="deleteDialogOpen"
      title="Удалить роль?"
      :description="roleToDelete ? `Роль «${roleToDelete.name}» и все связанные данные будут удалены безвозвратно.` : ''"
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="deletingRoleId === roleToDelete?.id"
      @update:open="(v) => { deleteDialogOpen = v; if (!v) closeDeleteDialog(); }"
      @confirm="confirmDeleteRole"
    />
  </div>
</template>
