<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Button, Card, CardContent, CardHeader, CardTitle, TooltipProvider } from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { useSiteContextStore } from '@/stores/siteContext';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { gamesApi, type GameCatalogItem } from '@/shared/api/gamesApi';
import { getGameSiteUrl } from '@/shared/lib/gameSiteUrl';
import { CharacterCard } from '@/entities/character';
import {
  Button as UiButton,
  Tooltip,
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from '@/shared/ui';

const router = useRouter();
const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const characters = ref<Character[]>([]);
const loading = ref(false);
const deletingId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const characterToDelete = ref<Character | null>(null);
const deleteError = ref<string | null>(null);

const availableGames = ref<GameCatalogItem[]>([]);
const availableGamesLoading = ref(false);
const availableGamesError = ref<string | null>(null);

async function loadCharacters() {
  if (!game.value?.id) return;
  loading.value = true;
  try {
    characters.value = await charactersApi.getCharacters(game.value.id);
  } finally {
    loading.value = false;
  }
}

async function loadAvailableGames() {
  availableGamesLoading.value = true;
  availableGamesError.value = null;
  try {
    availableGames.value = await gamesApi.getGamesCatalog();
  } catch (e) {
    availableGamesError.value = e instanceof Error ? e.message : 'Не удалось загрузить список игр';
  } finally {
    availableGamesLoading.value = false;
  }
}

function getMyCharactersUrlForGame(slug: string): string {
  return `${getGameSiteUrl(slug)}/my-characters`;
}

function goToGameMyCharacters(g: GameCatalogItem) {
  window.location.href = getMyCharactersUrlForGame(g.slug);
}

function openEdit(character: Character) {
  router.push({ name: 'my-characters-edit', params: { id: character.id } });
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
  if (!game.value) {
    loadAvailableGames();
  }
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <Card v-if="!game" class="border-0 shadow-none">
      <CardHeader>
        <CardTitle>Мои персонажи</CardTitle>
      </CardHeader>
      <CardContent>
        <p class="text-sm text-muted-foreground">
          Перейдите на страницу игры, чтобы управлять персонажами.
        </p>

        <div class="mt-4">
          <p v-if="availableGamesLoading" class="text-sm text-muted-foreground">Загрузка игр…</p>
          <p v-else-if="availableGamesError" class="text-sm text-destructive">{{ availableGamesError }}</p>
          <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Card
                v-for="(g, i) in availableGames"
                :key="g.id"
                class="group cursor-pointer overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 animate-in fade-in slide-in-from-bottom-3"
                :style="{
                  animationDelay: `${i * 80}ms`,
                  animationDuration: '400ms',
                  animationFillMode: 'backwards',
                }"
                @click="goToGameMyCharacters(g)"
              >
                <div class="relative aspect-[16/10] w-full overflow-hidden bg-muted">
                  <img
                    v-if="g.image_preview"
                    :src="g.image_preview"
                    :alt="g.name"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                  />
                  <div
                    v-else
                    class="flex h-full w-full items-center justify-center bg-gradient-to-br from-muted to-muted/70 text-4xl font-bold text-muted-foreground/60"
                  >
                    {{ g.name.slice(0, 2).toUpperCase() }}
                  </div>
                </div>
                <CardHeader class="space-y-2 pb-2">
                  <CardTitle class="line-clamp-1 text-lg leading-tight">{{ g.name }}</CardTitle>
                </CardHeader>
              </Card>
            </div>
          </template>
        </div>
      </CardContent>
    </Card>

    <template v-else>
      <div class="mx-auto max-w-[1040px]">
        <div class="mb-5 flex min-w-0 flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <h1 class="min-w-0 truncate text-2xl font-bold tracking-tight">
              Ваши персонажи
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Персонажи в {{ game.name }} для заявок, состава и активности в гильдиях
            </p>
          </div>
          <RouterLink :to="{ name: 'my-characters-create' }">
            <Button class="h-9 min-w-[44px] shrink-0 touch-manipulation">
              Добавить персонажа
            </Button>
          </RouterLink>
        </div>

        <Card class="border-0 shadow-none">
          <CardContent class="px-0 pb-0 pt-0">
            <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
            <p
              v-else-if="characters.length === 0"
              class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
            >
              Нет персонажей. Нажмите «Добавить персонажа», чтобы создать первого.
            </p>
            <template v-else>
              <TooltipProvider>
                <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <CharacterCard
                    v-for="c in characters"
                    :key="c.id"
                    :character="c"
                  >
                    <template #actions>
                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <UiButton
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="h-10 w-10 shrink-0 min-h-10 min-w-10 touch-manipulation"
                            aria-label="Действия"
                            title="Действия"
                          >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
                              <circle cx="12" cy="5" r="2" />
                              <circle cx="12" cy="12" r="2" />
                              <circle cx="12" cy="19" r="2" />
                            </svg>
                          </UiButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-44">
                          <DropdownMenuItem @click="openEdit(c)">
                            Редактировать
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            :disabled="deletingId === c.id"
                            @click="openDeleteDialog(c)"
                          >
                            Удалить
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </template>
                  </CharacterCard>
                </ul>
              </TooltipProvider>
            </template>
          </CardContent>
        </Card>
      </div>

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
