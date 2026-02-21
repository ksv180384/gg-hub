<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Button, Card, CardContent, CardHeader, CardTitle, TooltipProvider } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { useSiteContextStore } from '@/stores/siteContext';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import CharacterListItem from './CharacterListItem.vue';

const router = useRouter();
const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const characters = ref<Character[]>([]);
const loading = ref(false);
const deletingId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const characterToDelete = ref<Character | null>(null);
const deleteError = ref<string | null>(null);

async function loadCharacters() {
  if (!game.value?.id) return;
  loading.value = true;
  try {
    characters.value = await charactersApi.getCharacters(game.value.id);
  } finally {
    loading.value = false;
  }
}

function openEdit(character: Character) {
  router.push({ name: 'characters-edit', params: { id: character.id } });
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
        <RouterLink :to="{ name: 'characters-create' }">
          <Button class="min-h-11 min-w-[44px] shrink-0 touch-manipulation">
            Добавить персонажа
          </Button>
        </RouterLink>
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

      <ConfirmDialog
        :open="deleteDialogOpen"
        title="Удалить персонажа?"
        :description="characterToDelete ? `Персонаж «${characterToDelete.name}» будет удалён безвозвратно. Это действие нельзя отменить.` : ''"
        confirm-label="Удалить"
        cancel-label="Отмена"
        :loading="deletingId === characterToDelete?.id"
        @update:open="(v) => { deleteDialogOpen = v; if (!v) closeDeleteDialog(); }"
        @confirm="confirmDeleteCharacter"
      />
      <p v-if="deleteDialogOpen && deleteError" class="mt-4 text-sm text-destructive">{{ deleteError }}</p>
    </template>
  </div>
</template>
