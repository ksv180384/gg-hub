<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Select } from '@/shared/ui';
import type { SelectOption } from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import Badge from '@/shared/ui/badge/Badge.vue';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { guildsApi, type Guild, type GuildRosterMember, type GuildRole } from '@/shared/api/guildsApi';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const characterId = computed(() => Number(route.params.characterId));

const guild = ref<Guild | null>(null);
const member = ref<GuildRosterMember | null>(null);
const canExclude = ref(false);
const canChangeRole = ref(false);
const guildRoles = ref<GuildRole[]>([]);
const selectedRoleId = ref<string>('');
const changingRole = ref(false);
const roleError = ref<string | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const excludeDialogOpen = ref(false);
const excluding = ref(false);
const excludeError = ref<string | null>(null);

const roleOptions = computed<SelectOption[]>(() =>
  guildRoles.value.map((r) => ({ value: String(r.id), label: r.name }))
);

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

function backToRoster() {
  router.push({ name: 'guild-roster', params: { id: String(guildId.value) } });
}

async function loadData() {
  if (!guildId.value || !characterId.value || Number.isNaN(guildId.value) || Number.isNaN(characterId.value)) {
    loading.value = false;
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    const [guildData, rosterMemberRes] = await Promise.all([
      guildsApi.getGuild(guildId.value),
      guildsApi.getGuildRosterMember(guildId.value, characterId.value),
    ]);
    guild.value = guildData;
    member.value = rosterMemberRes.data;
    canExclude.value = rosterMemberRes.can_exclude;
    canChangeRole.value = rosterMemberRes.can_change_role;
    selectedRoleId.value = rosterMemberRes.data.guild_role
      ? String(rosterMemberRes.data.guild_role.id)
      : '';
    if (rosterMemberRes.can_change_role) {
      const rolesResult = await guildsApi.getGuildRoles(guildId.value);
      guildRoles.value = rolesResult.roles;
    } else {
      guildRoles.value = [];
    }
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) {
      error.value = 'Участник не найден в составе гильдии.';
    } else {
      error.value = err instanceof Error ? err.message : 'Ошибка загрузки';
    }
    member.value = null;
  } finally {
    loading.value = false;
  }
}

function openExcludeDialog() {
  excludeError.value = null;
  excludeDialogOpen.value = true;
}

function closeExcludeDialog() {
  if (!excluding.value) {
    excludeDialogOpen.value = false;
    excludeError.value = null;
  }
}

async function confirmExclude() {
  if (!guildId.value || !characterId.value || excluding.value) return;
  excluding.value = true;
  excludeError.value = null;
  try {
    await guildsApi.excludeGuildMember(guildId.value, characterId.value);
    excludeDialogOpen.value = false;
    backToRoster();
  } catch (e) {
    excludeError.value = e instanceof Error ? e.message : 'Не удалось исключить участника';
  } finally {
    excluding.value = false;
  }
}

async function onRoleChange(newRoleId: string) {
  if (!guildId.value || !characterId.value || changingRole.value) return;
  const id = Number(newRoleId);
  if (Number.isNaN(id) || id === member.value?.guild_role?.id) return;
  changingRole.value = true;
  roleError.value = null;
  try {
    await guildsApi.updateGuildMemberRole(guildId.value, characterId.value, id);
    const role = guildRoles.value.find((r) => r.id === id);
    if (member.value && role) {
      member.value = {
        ...member.value,
        guild_role: { id: role.id, name: role.name, slug: role.slug },
      };
    }
  } catch (e) {
    roleError.value = e instanceof Error ? e.message : 'Не удалось изменить роль';
    selectedRoleId.value = member.value?.guild_role ? String(member.value.guild_role.id) : '';
  } finally {
    changingRole.value = false;
  }
}

onMounted(() => loadData());
watch([guildId, characterId], () => loadData());
</script>

<template>
  <div class="container py-4 md:py-6 max-w-2xl mx-auto">
    <div class="mb-4">
      <Button variant="ghost" size="sm" class="-ml-2 shrink-0" @click="backToRoster">
        ← К составу гильдии
      </Button>
    </div>

    <Card v-if="error">
      <CardContent class="pt-6">
        <p class="text-sm text-destructive">{{ error }}</p>
        <Button class="mt-4" variant="outline" @click="backToRoster">К составу</Button>
      </CardContent>
    </Card>

    <Card v-else-if="loading">
      <CardContent class="py-8">
        <p class="text-sm text-muted-foreground">Загрузка…</p>
      </CardContent>
    </Card>

    <Card v-else-if="member" class="overflow-hidden">
      <CardHeader class="flex flex-row items-start justify-between gap-4">
        <div class="flex items-center gap-4">
          <Avatar
            :src="member.avatar_url ?? undefined"
            :alt="member.name"
            :fallback="avatarFallback(member.name)"
            class="h-16 w-16 shrink-0 md:h-20 md:w-20"
          />
          <div class="min-w-0 flex-1">
            <CardTitle class="text-xl">{{ member.name }}</CardTitle>
            <div v-if="canChangeRole && roleOptions.length" class="mt-2 flex flex-wrap items-center gap-2">
              <Select
                v-model="selectedRoleId"
                :options="roleOptions"
                placeholder="Роль"
                :disabled="changingRole"
                trigger-class="w-[180px]"
                @update:model-value="onRoleChange"
              />
              <span v-if="changingRole" class="text-xs text-muted-foreground">Сохранение…</span>
              <p v-if="roleError" class="text-xs text-destructive">{{ roleError }}</p>
            </div>
            <Badge v-else-if="member.guild_role" variant="secondary" class="mt-1">
              {{ member.guild_role.name }}
            </Badge>
          </div>
        </div>
        <Button
          v-if="canExclude"
          variant="destructive"
          size="sm"
          class="shrink-0"
          :disabled="excluding"
          @click="openExcludeDialog"
        >
          Исключить из гильдии
        </Button>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="member.game_classes.length > 0">
          <p class="mb-1 text-sm font-medium text-muted-foreground">Классы</p>
          <div class="flex flex-wrap gap-1">
            <Badge
              v-for="gc in member.game_classes"
              :key="gc.id"
              variant="outline"
              class="text-xs"
            >
              {{ gc.name_ru ?? gc.name }}
            </Badge>
          </div>
        </div>
        <div v-if="member.tags.length > 0">
          <p class="mb-1 text-sm font-medium text-muted-foreground">Теги</p>
          <div class="flex flex-wrap gap-1">
            <Badge
              v-for="tag in member.tags"
              :key="tag.id"
              variant="secondary"
              class="text-xs"
            >
              {{ tag.name }}
            </Badge>
          </div>
        </div>
      </CardContent>
    </Card>

    <ConfirmDialog
      :open="excludeDialogOpen"
      title="Исключить из гильдии"
      confirm-label="Исключить"
      confirm-variant="destructive"
      :loading="excluding"
      @confirm="confirmExclude"
      @update:open="(v) => { if (!v) closeExcludeDialog(); }"
    >
      <template #description>
        <p>Вы уверены, что хотите исключить этого участника из гильдии? Отменить действие будет нельзя.</p>
        <p v-if="excludeError" class="mt-2 text-sm text-destructive">{{ excludeError }}</p>
      </template>
    </ConfirmDialog>
  </div>
</template>
