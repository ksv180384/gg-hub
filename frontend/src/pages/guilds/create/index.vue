<script setup lang="ts">
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Label,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { useAuthStore } from '@/stores/auth';
import { guildsApi } from '@/shared/api/guildsApi';
import { gamesApi, type Game, type Localization, type Server } from '@/shared/api/gamesApi';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const siteContext = useSiteContextStore();
const authStore = useAuthStore();
const router = useRouter();

const name = ref('');
const selectedGameId = ref<string>('');
const selectedLocalizationId = ref<string>('');
const selectedServerId = ref<string>('');
const selectedLeaderCharacterId = ref<string>('');
const submitting = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});

const games = ref<Game[]>([]);
const localizations = ref<Localization[]>([]);
const servers = ref<Server[]>([]);
const characters = ref<Character[]>([]);
const loadingGames = ref(true);
const loadingCharacters = ref(false);

const selectedGame = computed(() => games.value.find((g) => String(g.id) === selectedGameId.value));
const availableLocalizations = computed(() => {
  if (!selectedGame.value?.localizations) return [];
  return selectedGame.value.localizations.filter((l) => l.is_active !== false);
});
const availableServers = computed(() => servers.value);
/** Персонажи пользователя на выбранном сервере (лидер должен быть с этого сервера). */
const charactersOnServer = computed(() => {
  const serverId = selectedServerId.value ? Number(selectedServerId.value) : 0;
  if (!serverId) return [];
  return characters.value.filter((c) => c.server_id === serverId);
});

const canSubmit = computed(
  () =>
    name.value.trim().length > 0 &&
    selectedLocalizationId.value !== '' &&
    selectedServerId.value !== '' &&
    selectedLeaderCharacterId.value !== ''
);

async function loadGames() {
  loadingGames.value = true;
  try {
    games.value = await gamesApi.getGames();
    if (siteContext.game) {
      const g = games.value.find((x) => x.id === siteContext.game!.id);
      if (g) selectedGameId.value = String(g.id);
    }
    if (!selectedGameId.value && games.value.length > 0) {
      selectedGameId.value = String(games.value[0].id);
    }
  } catch {
    error.value = 'Не удалось загрузить список игр';
  } finally {
    loadingGames.value = false;
  }
}

async function loadServers() {
  const gameId = selectedGame.value?.id;
  const locId = selectedLocalizationId.value ? Number(selectedLocalizationId.value) : 0;
  if (!gameId || !locId) {
    servers.value = [];
    return;
  }
  try {
    servers.value = await gamesApi.getServers(gameId, locId);
    selectedServerId.value = '';
  } catch {
    servers.value = [];
  }
}

watch(selectedGameId, () => {
  selectedLocalizationId.value = '';
  selectedServerId.value = '';
  servers.value = [];
  const g = selectedGame.value;
  if (g?.localizations?.length) {
    selectedLocalizationId.value = String(g.localizations[0].id);
  }
});

watch(selectedLocalizationId, () => {
  selectedServerId.value = '';
  selectedLeaderCharacterId.value = '';
  loadServers();
});

watch(selectedServerId, () => {
  selectedLeaderCharacterId.value = '';
});

async function loadCharacters() {
  const gameId = selectedGame.value?.id;
  if (!gameId) {
    characters.value = [];
    return;
  }
  loadingCharacters.value = true;
  try {
    characters.value = await charactersApi.getCharacters(gameId);
  } catch {
    characters.value = [];
  } finally {
    loadingCharacters.value = false;
  }
}

watch(selectedGameId, () => {
  loadCharacters();
});

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  error.value = null;
  fieldErrors.value = {};
  try {
    const guild = await guildsApi.createGuild({
      name: name.value.trim(),
      localization_id: Number(selectedLocalizationId.value),
      server_id: Number(selectedServerId.value),
      leader_character_id: Number(selectedLeaderCharacterId.value),
    });
    await router.push({ name: 'guild-settings', params: { id: String(guild.id) } });
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> }; 
    if (err.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(err.errors).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : String(v)])
      ) as Record<string, string>;
    }
    error.value = err.message ?? 'Не удалось создать гильдию';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  if (!authStore.isAuthenticated) {
    router.replace('/login');
    return;
  }
  loadGames();
});

watch(availableLocalizations, (list) => {
  if (selectedLocalizationId.value === '' && list.length > 0) {
    selectedLocalizationId.value = String(list[0].id);
  }
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Добавить гильдию</h1>
      <p class="mb-8 text-muted-foreground">
        Гильдия будет привязана к выбранному серверу. Укажите лидера — персонажа с этого сервера (персонаж может состоять только в одной гильдии).
      </p>

      <Card>
        <CardHeader>
          <CardTitle>Новая гильдия</CardTitle>
        </CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submit">
            <div v-if="error" class="rounded-md bg-destructive/10 p-4 text-sm text-destructive">
              {{ error }}
            </div>

            <div class="space-y-2">
              <Label for="guild-name">Название гильдии *</Label>
              <Input
                id="guild-name"
                v-model="name"
                placeholder="Название гильдии"
                required
              />
              <p v-if="fieldErrors.name" class="text-sm text-destructive">{{ fieldErrors.name }}</p>
            </div>

            <div v-if="!siteContext.game" class="space-y-2">
              <Label>Игра</Label>
              <SelectRoot v-model="selectedGameId" :disabled="loadingGames || !games.length">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Выберите игру" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="g in games"
                    :key="g.id"
                    :value="String(g.id)"
                  >
                    {{ g.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
            </div>

            <div class="space-y-2">
              <Label>Локализация *</Label>
              <SelectRoot
                v-model="selectedLocalizationId"
                :disabled="!availableLocalizations.length"
              >
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Выберите локализацию" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="loc in availableLocalizations"
                    :key="loc.id"
                    :value="String(loc.id)"
                  >
                    {{ loc.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
              <p v-if="fieldErrors.localization_id" class="text-sm text-destructive">
                {{ fieldErrors.localization_id }}
              </p>
            </div>

            <div class="space-y-2">
              <Label>Сервер *</Label>
              <SelectRoot
                v-model="selectedServerId"
                :disabled="!availableServers.length"
              >
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Выберите сервер" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="srv in availableServers"
                    :key="srv.id"
                    :value="String(srv.id)"
                  >
                    {{ srv.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
              <p v-if="fieldErrors.server_id" class="text-sm text-destructive">
                {{ fieldErrors.server_id }}
              </p>
            </div>

            <div class="space-y-2">
              <Label>Лидер гильдии *</Label>
              <SelectRoot
                v-model="selectedLeaderCharacterId"
                :disabled="!charactersOnServer.length || loadingCharacters"
              >
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Выберите персонажа на этом сервере" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="char in charactersOnServer"
                    :key="char.id"
                    :value="String(char.id)"
                  >
                    {{ char.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
              <p class="text-xs text-muted-foreground">
                Персонаж должен быть на выбранном сервере и не состоять в другой гильдии.
              </p>
              <p v-if="fieldErrors.leader_character_id" class="text-sm text-destructive">
                {{ fieldErrors.leader_character_id }}
              </p>
            </div>

            <div class="flex gap-3">
              <Button type="submit" :disabled="!canSubmit || submitting">
                {{ submitting ? 'Создание…' : 'Создать гильдию' }}
              </Button>
              <Button type="button" variant="outline" @click="router.push({ name: 'guilds' })">
                Отмена
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
