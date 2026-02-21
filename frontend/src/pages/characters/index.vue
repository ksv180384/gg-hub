<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
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
const deletingId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const characterToDelete = ref<Character | null>(null);
const deleteError = ref<string | null>(null);
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

function openDeleteDialog(character: Character) {
  characterToDelete.value = character;
  deleteError.value = null;
  deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
  if (!deletingId.value) {
    characterToDelete.value = null;
    deleteError.value = null;
    deleteDialogOpen.value = false;
  }
}

async function confirmDeleteCharacter() {
  const character = characterToDelete.value;
  if (!character) return;
  deletingId.value = character.id;
  deleteError.value = null;
  try {
    await charactersApi.deleteCharacter(character.id);
    characters.value = characters.value.filter((c) => c.id !== character.id);
    if (editingId.value === character.id) {
      editingId.value = null;
      formOpen.value = false;
    }
    characterToDelete.value = null;
    deleteDialogOpen.value = false;
  } catch (e) {
    deleteError.value = e instanceof Error ? e.message : 'Не удалось удалить персонажа';
  } finally {
    deletingId.value = null;
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
                  :deleting="deletingId === c.id"
                  @edit="openEdit(c)"
                  @delete="openDeleteDialog(c)"
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

      <DialogRoot v-model:open="deleteDialogOpen" @update:open="(v: boolean) => !v && closeDeleteDialog()">
        <DialogPortal>
          <DialogOverlay
            class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
          />
          <DialogContent
            class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            :aria-describedby="undefined"
          >
            <DialogTitle class="text-lg font-semibold">Удалить персонажа?</DialogTitle>
            <DialogDescription class="text-sm text-muted-foreground">
              Персонаж «{{ characterToDelete?.name }}» будет удалён безвозвратно. Это действие нельзя отменить.
            </DialogDescription>
            <p v-if="deleteError" class="text-sm text-destructive">{{ deleteError }}</p>
            <div class="flex justify-end gap-2 pt-4">
              <Button variant="outline" :disabled="!!deletingId" @click="deleteDialogOpen = false">
                Отмена
              </Button>
              <Button
                variant="destructive"
                :disabled="deletingId === characterToDelete?.id"
                @click="confirmDeleteCharacter()"
              >
                {{ deletingId === characterToDelete?.id ? 'Удаление…' : 'Удалить' }}
              </Button>
            </div>
          </DialogContent>
        </DialogPortal>
      </DialogRoot>
    </template>
  </div>
</template>
