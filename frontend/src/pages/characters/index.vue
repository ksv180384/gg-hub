<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Button, Card, CardContent, CardHeader, CardTitle, TooltipProvider } from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import CharacterListItem from './CharacterListItem.vue';
import CharacterFormSheet from './CharacterFormSheet.vue';

const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const characters = ref<Character[]>([]);
const loading = ref(false);
const formOpen = ref(false);
const editingId = ref<number | null>(null);
const gameFull = ref<Game | null>(null);
const gameLoading = ref(false);

const editingCharacter = computed(() => {
  if (editingId.value == null) return null;
  return characters.value.find((c) => c.id === editingId.value) ?? null;
});

async function loadCharacters() {
  if (!game.value?.id) return;
  loading.value = true;
  try {
    characters.value = await charactersApi.getCharacters(game.value.id);
  } finally {
    loading.value = false;
  }
}

async function loadGame() {
  if (!game.value?.id) return;
  gameLoading.value = true;
  try {
    gameFull.value = await gamesApi.getGame(game.value.id);
  } finally {
    gameLoading.value = false;
  }
}

function openCreate() {
  editingId.value = null;
  formOpen.value = true;
  loadGame();
}

function openEdit(character: Character) {
  editingId.value = character.id;
  formOpen.value = true;
  loadGame();
}

function onFormSaved() {
  loadCharacters();
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
          Перейдите на страницу игры (поддомен игры, например <strong>wow.gg-hub.local</strong>), чтобы управлять персонажами.
        </p>
      </CardContent>
    </Card>

    <template v-else>
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold sm:text-2xl">Персонажи</h1>
        <Button class="min-h-11 min-w-[44px] shrink-0 touch-manipulation" @click="openCreate">
          Добавить персонажа
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Ваши персонажи в {{ game.name }}</CardTitle>
        </CardHeader>
        <CardContent>
          <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="characters.length === 0" class="text-sm text-muted-foreground">
            Нет персонажей. Нажмите «Добавить персонажа», чтобы создать первого.
          </p>
          <template v-else>
            <TooltipProvider>
              <ul class="space-y-3">
                <CharacterListItem
                  v-for="c in characters"
                  :key="c.id"
                  :character="c"
                  @edit="openEdit(c)"
                />
              </ul>
            </TooltipProvider>
          </template>
        </CardContent>
      </Card>

      <CharacterFormSheet
        :open="formOpen"
        :game-full="gameFull"
        :game-loading="gameLoading"
        :editing-character="editingCharacter"
        :game-id="game.id"
        @update:open="formOpen = $event"
        @saved="onFormSaved"
      />
    </template>
  </div>
</template>
