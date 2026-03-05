<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { RouterLink } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import Badge from '@/shared/ui/badge/Badge.vue';
import { guildsApi, type Guild, type GuildRosterMember } from '@/shared/api/guildsApi';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

const guild = ref<Guild | null>(null);
const roster = ref<GuildRosterMember[]>([]);
const loading = ref(true);
const accessDenied = ref(false);
const error = ref<string | null>(null);

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

onMounted(async () => {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  loading.value = true;
  accessDenied.value = false;
  error.value = null;
  try {
    guild.value = await guildsApi.getGuild(guildId.value);
  } catch {
    guild.value = null;
    loading.value = false;
    return;
  }
  try {
    roster.value = await guildsApi.getGuildRoster(guildId.value);
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 403) {
      accessDenied.value = true;
    } else {
      error.value = err instanceof Error ? err.message : 'Ошибка загрузки состава';
    }
    roster.value = [];
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="container py-4 md:py-6">
    <Card>
      <CardHeader>
        <CardTitle>Состав гильдии</CardTitle>
      </CardHeader>
      <CardContent>
        <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>

        <template v-else-if="accessDenied">
          <p class="text-sm text-muted-foreground">
            Состав гильдии доступен только участникам гильдии.
          </p>
        </template>

        <template v-else-if="error">
          <p class="text-sm text-destructive">{{ error }}</p>
        </template>

        <template v-else-if="roster.length === 0 && guild">
          <p class="text-sm text-muted-foreground">В гильдии пока никого нет.</p>
        </template>

        <div
          v-else
          class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
          <RouterLink
            v-for="member in roster"
            :key="member.character_id"
            :to="{ name: 'guild-roster-member', params: { id: String(guildId), characterId: String(member.character_id) } }"
            class="block transition-opacity hover:opacity-90 focus:opacity-90"
          >
            <Card class="h-full overflow-hidden">
              <CardContent class="flex flex-col items-start gap-3 p-4">
                <div class="flex w-full items-center gap-3">
                  <Avatar
                    :src="member.avatar_url ?? undefined"
                    :alt="member.name"
                    :fallback="avatarFallback(member.name)"
                    class="h-12 w-12 shrink-0 md:h-14 md:w-14"
                  />
                  <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ member.name }}</p>
                    <Badge
                      v-if="member.guild_role"
                      variant="secondary"
                      class="mt-1 text-xs"
                    >
                      {{ member.guild_role.name }}
                    </Badge>
                  </div>
                </div>
                <div v-if="member.game_classes.length > 0" class="flex flex-wrap gap-1">
                  <Badge
                    v-for="gc in member.game_classes"
                    :key="gc.id"
                    variant="outline"
                    class="text-xs"
                  >
                    {{ gc.name_ru ?? gc.name }}
                  </Badge>
                </div>
                <div v-if="member.tags.length > 0" class="flex flex-wrap gap-1">
                  <Badge
                    v-for="tag in member.tags"
                    :key="tag.id"
                    variant="secondary"
                    class="text-xs"
                  >
                    {{ tag.name }}
                  </Badge>
                </div>
              </CardContent>
            </Card>
          </RouterLink>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
