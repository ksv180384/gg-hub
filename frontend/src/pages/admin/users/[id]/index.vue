<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Label,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
  TooltipProvider,
  Tooltip,
} from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import {
  accessApi,
  type AdminUserDto,
  type PermissionGroupDto,
  type RoleDto,
} from '@/shared/api/accessApi';

const PERMISSION_BAN_USER = 'zablokirovat-polzovatelia';
const PERMISSION_CHANGE_USER_ROLE = 'izmeniat-rol-polzovatelia';
const PERMISSION_CHANGE_USER_PERMISSIONS = 'izmeniat-prava-polzovatelia';
const auth = useAuthStore();
const canBanUser = computed(() => auth.hasPermission(PERMISSION_BAN_USER));
const canChangeUserRole = computed(() => auth.hasPermission(PERMISSION_CHANGE_USER_ROLE));
const canChangeUserPermissions = computed(() => auth.hasPermission(PERMISSION_CHANGE_USER_PERMISSIONS));

const router = useRouter();
const route = useRoute();
const userId = computed(() => Number(route.params.id));

const user = ref<AdminUserDto | null>(null);
const roles = ref<RoleDto[]>([]);
const groups = ref<PermissionGroupDto[]>([]);
const selectedRoleId = ref<number | null>(null);
const selectedPermissionIds = ref<number[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);
const banning = ref(false);

function syncFromUser(u: AdminUserDto) {
  selectedRoleId.value = u.roles?.[0]?.id ?? null;
  const permSlugs = u.permissions ?? [];
  const allPerms = groups.value.flatMap((g) => g.permissions ?? []);
  selectedPermissionIds.value = permSlugs
    .map((slug) => allPerms.find((p) => p.slug === slug)?.id)
    .filter((id): id is number => id != null);
}

const ROLE_NONE_VALUE = '__none__';

const selectedRoleValue = computed({
  get: () => (selectedRoleId.value == null ? ROLE_NONE_VALUE : String(selectedRoleId.value)),
  set: (v: string) => {
    selectedRoleId.value = v === ROLE_NONE_VALUE ? null : Number(v);
  },
});

function togglePermission(id: number) {
  const idx = selectedPermissionIds.value.indexOf(id);
  if (idx === -1) selectedPermissionIds.value = [...selectedPermissionIds.value, id];
  else selectedPermissionIds.value = selectedPermissionIds.value.filter((x) => x !== id);
}

const isBanned = computed(() => !!user.value?.banned_at);

async function loadUser() {
  const u = await accessApi.getUser(userId.value);
  user.value = u;
  syncFromUser(u);
}

onMounted(async () => {
  try {
    const [rolesData, groupsData] = await Promise.all([
      accessApi.getRoles(),
      accessApi.getPermissionGroups(),
    ]);
    roles.value = rolesData;
    groups.value = groupsData;
    await loadUser();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки';
  } finally {
    loading.value = false;
  }
});

watch(userId, () => loadUser(), { immediate: false });

async function saveRolesPermissions() {
  if (!user.value) return;
  saving.value = true;
  error.value = null;
  try {
    await accessApi.updateUserRolesPermissions(user.value.id, {
      ...(canChangeUserRole.value
        ? { role_ids: selectedRoleId.value != null ? [selectedRoleId.value] : [] }
        : {}),
      ...(canChangeUserPermissions.value ? { permission_ids: selectedPermissionIds.value } : {}),
    });
    await loadUser();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка сохранения';
  } finally {
    saving.value = false;
  }
}

async function toggleBan() {
  if (!user.value) return;
  banning.value = true;
  error.value = null;
  try {
    user.value = await accessApi.updateUserBan(user.value.id, !isBanned.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Ошибка';
  } finally {
    banning.value = false;
  }
}

const hasAnyPermissions = computed(() => groups.value.some((g) => g.permissions?.length));
</script>

<template>
  <div class="container max-w-5xl py-6">
    <p v-if="error" class="mb-4 text-sm text-destructive">{{ error }}</p>
    <div v-if="loading" class="text-sm text-muted-foreground">Загрузка…</div>
    <template v-else-if="user">
      <div class="mb-4 flex items-center gap-2">
        <Button variant="ghost" size="sm" @click="router.push('/admin/users')">← К списку</Button>
      </div>
      <div class="flex flex-col gap-6 sm:flex-row">
        <!-- Левая колонка: карточка пользователя + бан -->
        <div class="sm:w-80 sm:shrink-0 sm:sticky sm:top-6 sm:self-start">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">{{ user.name }}</CardTitle>
              <p class="text-sm text-muted-foreground">{{ user.email }}</p>
            </CardHeader>
            <CardContent class="space-y-4">
              <div v-if="user.banned_at" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                Заблокирован
              </div>
              <div v-if="canBanUser" class="flex flex-col gap-2">
                <Label>Блокировка</Label>
                <Button
                  :variant="isBanned ? 'default' : 'destructive'"
                  :disabled="banning"
                  @click="toggleBan"
                >
                  {{ isBanned ? 'Разблокировать' : 'Заблокировать' }}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
        <!-- Правая колонка: роли и права -->
        <div class="min-w-0 flex-1 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Роли</CardTitle>
              <p class="text-xs text-muted-foreground">
                {{ canChangeUserRole ? 'Назначьте роль пользователю.' : 'Текущая роль (изменение доступно только с правом «Изменение роли пользователя»).' }}
              </p>
            </CardHeader>
            <CardContent>
              <template v-if="canChangeUserRole">
                <p class="mb-2 text-xs text-muted-foreground">Можно назначить только одну роль.</p>
                <SelectRoot v-model="selectedRoleValue">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Выберите роль" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="ROLE_NONE_VALUE">Без роли</SelectItem>
                    <SelectItem
                      v-for="r in roles"
                      :key="r.id"
                      :value="String(r.id)"
                    >
                      {{ r.name }} ({{ r.slug }})
                    </SelectItem>
                  </SelectContent>
                </SelectRoot>
              </template>
              <p v-else class="text-sm text-muted-foreground">
                {{ user.roles?.[0] ? `${user.roles[0].name} (${user.roles[0].slug})` : 'Без роли' }}
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Права</CardTitle>
              <p class="text-xs text-muted-foreground">
                {{ canChangeUserPermissions ? 'Дополнительные права (сверх ролей).' : 'Текущие права (изменение доступно только с правом «Изменение прав пользователя»).' }}
              </p>
            </CardHeader>
            <CardContent>
              <template v-if="canChangeUserPermissions">
                <TooltipProvider>
                  <div class="max-h-64 space-y-4 overflow-y-auto rounded-md border p-3">
                    <div v-for="group in groups" :key="group.id" class="space-y-2">
                      <div class="text-xs font-medium text-muted-foreground">{{ group.name }}</div>
                      <div class="space-y-1.5 pl-1">
                        <Tooltip
                          v-for="p in (group.permissions ?? [])"
                          :key="p.id"
                          :content="p.description ?? ''"
                        >
                          <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input
                              type="checkbox"
                              :checked="(selectedPermissionIds ?? []).includes(p.id)"
                              @change="togglePermission(p.id)"
                            />
                            <span>{{ p.name }}</span>
                            <span class="text-muted-foreground">({{ p.slug }})</span>
                          </label>
                        </Tooltip>
                      </div>
                    </div>
                    <p v-if="!hasAnyPermissions" class="text-sm text-muted-foreground">Нет прав.</p>
                  </div>
                </TooltipProvider>
              </template>
              <p v-else class="text-sm text-muted-foreground">
                {{ user.permissions?.length ? user.permissions.join(', ') : 'Нет дополнительных прав' }}
              </p>
              <Button
                v-if="canChangeUserRole || canChangeUserPermissions"
                class="mt-4"
                :disabled="saving"
                @click="saveRolesPermissions"
              >
                {{ saving ? 'Сохранение…' : 'Сохранить роли и права' }}
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </template>
  </div>
</template>
