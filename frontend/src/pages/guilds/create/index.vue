<script setup lang="ts">
import {
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Label,
  Select,
  type SelectOption,
} from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { useAuthStore } from '@/stores/auth';
import { guildsApi } from '@/shared/api/guildsApi';
import { gamesApi, type Game, type Localization, type Server } from '@/shared/api/gamesApi';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
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
/** Персонажи, доступные для лидера: не состоят в гильдии и не лидер другой гильдии (загружаются при выборе сервера). */
const charactersForLeader = ref<Character[]>([]);
const tags = ref<Tag[]>([]);
const selectedTagIds = ref<number[]>([]);
const loadingGames = ref(true);
const loadingCharacters = ref(false);

const selectedGame = computed(() => games.value.find((g) => String(g.id) === selectedGameId.value));
const availableLocalizations = computed(() => {
  if (!selectedGame.value?.localizations) return [];
  return selectedGame.value.localizations.filter((l) => l.is_active !== false);
});
const availableServers = computed(() => servers.value);

const gameOptions = computed<SelectOption[]>(() =>
  games.value.map((g) => ({ value: String(g.id), label: g.name }))
);
const localizationOptions = computed<SelectOption[]>(() =>
  availableLocalizations.value.map((loc) => ({ value: String(loc.id), label: loc.name }))
);
const serverOptions = computed<SelectOption[]>(() =>
  availableServers.value.map((s) => ({ value: String(s.id), label: s.name }))
);
const leaderCharacterOptions = computed<SelectOption[]>(() =>
  charactersForLeader.value.map((c) => ({ value: String(c.id), label: c.name }))
);

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
    const firstGame = games.value[0];
    if (!selectedGameId.value && firstGame) {
      selectedGameId.value = String(firstGame.id);
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
  const firstLoc = g?.localizations?.[0];
  if (firstLoc) {
    selectedLocalizationId.value = String(firstLoc.id);
  }
});

watch(selectedLocalizationId, () => {
  selectedServerId.value = '';
  selectedLeaderCharacterId.value = '';
  loadServers();
});

watch(selectedServerId, () => {
  selectedLeaderCharacterId.value = '';
  loadCharactersForLeader();
});

/** Загружает персонажей, доступных для выбора лидером (не в гильдии, не лидер другой гильдии). */
async function loadCharactersForLeader() {
  const gameId = selectedGame.value?.id;
  const serverId = selectedServerId.value ? Number(selectedServerId.value) : 0;
  if (!gameId || !serverId) {
    charactersForLeader.value = [];
    return;
  }
  loadingCharacters.value = true;
  try {
    charactersForLeader.value = await charactersApi.getCharactersForGuildLeader(gameId, serverId);
  } catch {
    charactersForLeader.value = [];
  } finally {
    loadingCharacters.value = false;
  }
}

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
      ...(selectedTagIds.value.length > 0 && { tag_ids: selectedTagIds.value }),
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

function toggleTag(tagId: number) {
  const idx = selectedTagIds.value.indexOf(tagId);
  if (idx >= 0) {
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== tagId);
  } else {
    selectedTagIds.value = [...selectedTagIds.value, tagId];
  }
}

const tagDeleteDialogOpen = ref(false);
const tagToDelete = ref<Tag | null>(null);
const tagDeleteLoading = ref(false);

function isMyTag(tag: Tag): boolean {
  const u = authStore.user;
  return u != null && tag.created_by_user_id != null && Number(tag.created_by_user_id) === u.id;
}

function openTagDeleteConfirm(tag: Tag) {
  tagToDelete.value = tag;
  tagDeleteDialogOpen.value = true;
}

async function confirmDeleteTagForever() {
  const t = tagToDelete.value;
  if (!t || tagDeleteLoading.value) return;
  tagDeleteLoading.value = true;
  try {
    await tagsApi.deleteTag(t.id);
    selectedTagIds.value = selectedTagIds.value.filter((id) => id !== t.id);
    try {
      tags.value = await tagsApi.getTags(false);
    } catch {
      /* ignore */
    }
    tagDeleteDialogOpen.value = false;
    tagToDelete.value = null;
  } finally {
    tagDeleteLoading.value = false;
  }
}

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.replace('/login');
    return;
  }
  loadGames();
  try {
    tags.value = await tagsApi.getTags(false);
  } catch {
    tags.value = [];
  }
});

watch(availableLocalizations, (list) => {
  const first = list[0];
  if (selectedLocalizationId.value === '' && first) {
    selectedLocalizationId.value = String(first.id);
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
              <Select
                v-model="selectedGameId"
                :options="gameOptions"
                placeholder="Выберите игру"
                :disabled="loadingGames || !games.length"
                trigger-class="w-full"
              />
            </div>

            <div class="space-y-2">
              <Label>Локализация *</Label>
              <Select
                v-model="selectedLocalizationId"
                :options="localizationOptions"
                placeholder="Выберите локализацию"
                :disabled="!availableLocalizations.length"
                trigger-class="w-full"
              />
              <p v-if="fieldErrors.localization_id" class="text-sm text-destructive">
                {{ fieldErrors.localization_id }}
              </p>
            </div>

            <div class="space-y-2">
              <Label>Сервер *</Label>
              <Select
                v-model="selectedServerId"
                :options="serverOptions"
                placeholder="Выберите сервер"
                :disabled="!availableServers.length"
                trigger-class="w-full"
              />
              <p v-if="fieldErrors.server_id" class="text-sm text-destructive">
                {{ fieldErrors.server_id }}
              </p>
            </div>

            <div v-if="tags.length" class="space-y-2">
              <Label>Теги</Label>
              <div class="flex flex-wrap items-center gap-2">
                <div
                  v-for="tag in tags"
                  :key="tag.id"
                  class="inline-flex items-center gap-0.5"
                >
                  <button
                    type="button"
                    class="inline-flex rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    @click="toggleTag(tag.id)"
                  >
                    <Badge
                      :variant="selectedTagIds.includes(tag.id) ? 'outline' : 'secondary'"
                    >
                      {{ tag.name }}
                    </Badge>
                  </button>
                  <button
                    v-if="isMyTag(tag)"
                    type="button"
                    class="inline-flex shrink-0 rounded p-1 text-destructive outline-none hover:bg-destructive/10 focus-visible:ring-2 focus-visible:ring-ring"
                    title="Удалить тег"
                    aria-label="Удалить тег"
                    @click.stop.prevent="openTagDeleteConfirm(tag)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M3 6h18" />
                      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                      <line x1="10" x2="10" y1="11" y2="17" />
                      <line x1="14" x2="14" y1="11" y2="17" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <div class="space-y-2">
              <Label>Лидер гильдии *</Label>
              <Select
                v-model="selectedLeaderCharacterId"
                :options="leaderCharacterOptions"
                placeholder="Выберите персонажа на этом сервере"
                :disabled="!charactersForLeader.length || loadingCharacters"
                trigger-class="w-full"
              />
              <p class="text-xs text-muted-foreground">
                Показываются только персонажи, которые не состоят ни в какой гильдии (и не являются лидером другой).
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

    <ConfirmDialog
      :open="tagDeleteDialogOpen"
      :title="tagToDelete ? `Удалить тег «${tagToDelete.name}»?` : 'Удалить тег?'"
      description="Тег исчезнет из всех персонажей и гильдий. Это действие нельзя отменить."
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="tagDeleteLoading"
      confirm-variant="destructive"
      @update:open="(v) => { tagDeleteDialogOpen = v; }"
      @confirm="confirmDeleteTagForever"
    />
  </div>
</template>
