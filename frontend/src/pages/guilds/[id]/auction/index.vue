<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Avatar,
  Badge,
} from '@/shared/ui';
import { SpinWheel } from '@/widgets/spin-wheel';
import { guildsApi, type GuildRosterMember } from '@/shared/api/guildsApi';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

const roster = ref<GuildRosterMember[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const searchQuery = ref('');
/** character_id в порядке добавления на колесо */
const wheelCharacterIds = ref<number[]>([]);

const filteredRoster = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return roster.value;
  return roster.value.filter((m) => m.name.toLowerCase().includes(q));
});

const wheelOptions = computed(() =>
  wheelCharacterIds.value
    .map((id) => roster.value.find((m) => m.character_id === id)?.name)
    .filter((n): n is string => Boolean(n))
);

function isInWheel(characterId: number) {
  return wheelCharacterIds.value.includes(characterId);
}

function addToWheel(member: GuildRosterMember) {
  if (!wheelCharacterIds.value.includes(member.character_id)) {
    wheelCharacterIds.value = [...wheelCharacterIds.value, member.character_id];
  }
}

function removeFromWheel(characterId: number) {
  wheelCharacterIds.value = wheelCharacterIds.value.filter((id) => id !== characterId);
}

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

async function loadRoster() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  loading.value = true;
  error.value = null;
  try {
    roster.value = await guildsApi.getGuildRoster(guildId.value);
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 403) {
      error.value = 'Состав гильдии доступен только участникам.';
    } else {
      error.value = err instanceof Error ? err.message : 'Ошибка загрузки состава';
    }
    roster.value = [];
  } finally {
    loading.value = false;
  }
}

watch(guildId, loadRoster, { immediate: true });
</script>

<template>
  <div class="container py-6">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
      <!-- Колесо слева -->
      <div class="shrink-0">
        <Card>
          <CardHeader>
            <CardTitle>Рулетка гильдии</CardTitle>
          </CardHeader>
          <CardContent class="flex flex-col items-center gap-6">
            <p class="text-sm text-muted-foreground text-center">
              Добавьте участников справа и крутите колесо.
            </p>
            <SpinWheel
              :options="wheelOptions.length > 0 ? wheelOptions : ['Добавьте участников']"
              :size="360"
              :duration="4000"
            />
          </CardContent>
        </Card>
      </div>

      <!-- Список участников справа -->
      <Card class="min-w-0 flex-1 lg:max-w-sm">
        <CardHeader>
          <CardTitle>Участники гильдии</CardTitle>
          <p class="text-sm text-muted-foreground">
            Добавьте участников на колесо для розыгрыша.
          </p>
          <Input
            v-model="searchQuery"
            type="search"
            placeholder="Поиск по имени…"
            class="mt-2"
          />
        </CardHeader>
        <CardContent>
          <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="error" class="text-sm text-destructive">{{ error }}</p>
          <p v-else-if="filteredRoster.length === 0" class="text-sm text-muted-foreground">
            {{ searchQuery.trim() ? 'Никого не найдено.' : 'В гильдии пока никого нет.' }}
          </p>
          <ul v-else class="space-y-2">
            <li
              v-for="member in filteredRoster"
              :key="member.character_id"
              class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
            >
              <div class="flex min-w-0 flex-1 items-center gap-3">
                <Avatar
                  :src="member.avatar_url ?? undefined"
                  :alt="member.name"
                  :fallback="avatarFallback(member.name)"
                  class="h-9 w-9 shrink-0"
                />
                <div class="min-w-0">
                  <p class="truncate font-medium text-sm">{{ member.name }}</p>
                  <Badge
                    v-if="member.guild_role"
                    variant="secondary"
                    class="mt-0.5 text-xs"
                  >
                    {{ member.guild_role.name }}
                  </Badge>
                </div>
              </div>
              <Button
                v-if="isInWheel(member.character_id)"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="removeFromWheel(member.character_id)"
              >
                Убрать
              </Button>
              <Button
                v-else
                variant="default"
                size="sm"
                class="shrink-0"
                @click="addToWheel(member)"
              >
                Добавить
              </Button>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
