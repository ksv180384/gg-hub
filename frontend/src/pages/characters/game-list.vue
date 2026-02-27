<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import CharacterClassBadge from './CharacterClassBadge.vue';

const router = useRouter();

const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const characters = ref<Character[]>([]);
const loading = ref(false);

async function loadCharacters() {
  if (!game.value?.id) return;
  loading.value = true;
  try {
    characters.value = await charactersApi.getGameCharacters(game.value.id);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadCharacters();
});
</script>

<template>
  <div class="container py-6">
    <Card v-if="!game" class="border-destructive/50">
      <CardHeader>
        <CardTitle>Персонажи</CardTitle>
      </CardHeader>
      <CardContent>
        <p class="text-sm text-muted-foreground">
          Перейдите на страницу игры (поддомен игры), чтобы увидеть список персонажей.
        </p>
      </CardContent>
    </Card>

    <template v-else>
      <div class="mb-4">
        <h1 class="text-xl font-semibold sm:text-2xl">Персонажи</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Все персонажи игры {{ game.name }}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Список персонажей</CardTitle>
        </CardHeader>
        <CardContent>
          <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="characters.length === 0" class="text-sm text-muted-foreground">
            В игре пока нет персонажей.
          </p>
          <ul v-else class="space-y-3">
            <li
              v-for="c in characters"
              :key="c.id"
              class="flex flex-wrap items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50 sm:gap-4 cursor-pointer"
              @click="router.push({ name: 'game-character-show', params: { id: c.id } })"
            >
              <Avatar
                :src="c.avatar_url ?? undefined"
                :alt="c.name"
                :fallback="c.name.slice(0, 2).toUpperCase()"
                class="h-12 w-12 shrink-0"
              />
              <div class="min-w-0 flex-1">
                <p class="font-medium">{{ c.name }}</p>
                <p class="text-sm text-muted-foreground">
                  <span v-if="c.localization?.name">{{ c.localization.name }}</span>
                  <template v-if="c.localization?.name && c.server?.name"> · </template>
                  <span v-if="c.server?.name">{{ c.server.name }}</span>
                  <template v-if="!c.localization?.name && !c.server?.name">—</template>
                </p>
                <div v-if="c.game_classes?.length" class="mt-1 flex flex-wrap items-center gap-1.5">
                  <CharacterClassBadge
                    v-for="gc in c.game_classes"
                    :key="gc.id"
                    :game-class="gc"
                  />
                </div>
              </div>
            </li>
          </ul>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
