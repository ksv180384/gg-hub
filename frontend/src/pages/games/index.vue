<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Badge,
  Sheet,
  Input,
  Label,
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { gamesApi, type Game, type Localization } from '@/shared/api/gamesApi';
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const siteContext = useSiteContextStore();
const router = useRouter();
const games = ref<Game[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

const sheetOpen = ref(false);
const selectedGame = ref<Game | null>(null);
const locCode = ref('');
const locName = ref('');
const locSubmitting = ref(false);
const locError = ref<string | null>(null);
const deletingGameId = ref<number | null>(null);

const deleteDialogOpen = ref(false);
const gameToDelete = ref<Game | null>(null);

const canEdit = computed(() => siteContext.isAdmin);

function openDeleteDialog(game: Game) {
  gameToDelete.value = game;
  deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
  if (!deletingGameId.value) {
    gameToDelete.value = null;
    deleteDialogOpen.value = false;
  }
}

async function loadGames() {
  loading.value = true;
  error.value = null;
  try {
    games.value = await gamesApi.getGames();
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось загрузить игры';
  } finally {
    loading.value = false;
  }
}

function openAddLocalization(game: Game) {
  selectedGame.value = game;
  locCode.value = '';
  locName.value = '';
  locError.value = null;
  sheetOpen.value = true;
}

async function submitLocalization() {
  if (!selectedGame.value) return;
  locSubmitting.value = true;
  locError.value = null;
  try {
    await gamesApi.createLocalization(selectedGame.value.id, {
      code: locCode.value.trim(),
      name: locName.value.trim(),
    });
    const idx = games.value.findIndex((g) => g.id === selectedGame.value!.id);
    const gameAtIdx = idx !== -1 ? games.value[idx] : undefined;
    if (gameAtIdx?.localizations) {
      const updated = await gamesApi.getGames();
      const found = updated.find((g) => g.id === selectedGame.value!.id);
      if (found && idx !== -1) games.value[idx] = found;
    } else {
      await loadGames();
    }
    sheetOpen.value = false;
    selectedGame.value = null;
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> };
    locError.value =
      err.errors?.code?.[0] ?? err.errors?.name?.[0] ?? err.message ?? 'Ошибка добавления локализации';
  } finally {
    locSubmitting.value = false;
  }
}

async function confirmDeleteGame() {
  const game = gameToDelete.value;
  if (!game) return;
  deletingGameId.value = game.id;
  error.value = null;
  try {
    await gamesApi.deleteGame(game.id);
    games.value = games.value.filter((g) => g.id !== game.id);
    gameToDelete.value = null;
    deleteDialogOpen.value = false;
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    error.value = err.message ?? 'Не удалось удалить игру';
  } finally {
    deletingGameId.value = null;
  }
}

onMounted(() => loadGames());
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Игры</h1>
      <p class="mb-8 text-muted-foreground">
        Список игр. Добавлять, редактировать и удалять игры можно только с админского субдомена (admin.gg-hub.local).
      </p>

      <div v-if="canEdit" class="mb-6">
        <Button @click="router.push('/games/create')">Добавить игру</Button>
      </div>

      <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ error }}
      </div>

      <div v-if="loading" class="text-muted-foreground">Загрузка...</div>

      <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <Card
          v-for="(g, i) in games"
          :key="g.id"
          class="group overflow-hidden transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 animate-in fade-in slide-in-from-bottom-3"
          :style="{
            animationDelay: `${i * 80}ms`,
            animationDuration: '400ms',
            animationFillMode: 'backwards',
          }"
        >
          <!-- Прямоугольная картинка игры (16:10) -->
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
            <div class="flex items-center gap-1">
              <CardTitle class="line-clamp-1 flex-1 min-w-0 text-lg leading-tight">{{ g.name }}</CardTitle>
              <DropdownMenu v-if="canEdit">
                <DropdownMenuTrigger as-child>
                  <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 shrink-0 text-muted-foreground"
                    :disabled="deletingGameId === g.id"
                    aria-label="Действия с игрой"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <circle cx="12" cy="12" r="1" />
                      <circle cx="12" cy="5" r="1" />
                      <circle cx="12" cy="19" r="1" />
                    </svg>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuItem @select="router.push(`/games/${g.id}/edit`)">
                    Редактировать
                  </DropdownMenuItem>
                  <DropdownMenuItem @select="openAddLocalization(g)">
                    Добавить локализацию
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    class="text-destructive focus:text-destructive"
                    :disabled="deletingGameId === g.id"
                    @select="openDeleteDialog(g)"
                  >
                    Удалить игру
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <p class="line-clamp-2 text-sm text-muted-foreground">{{ g.description || '—' }}</p>
            <div v-if="g.localizations?.length" class="flex flex-wrap gap-1.5 pt-0.5">
              <Badge v-for="loc in g.localizations" :key="loc.id" variant="secondary" class="text-xs font-medium">
                {{ loc.code }}: {{ loc.name }}
              </Badge>
            </div>
          </CardHeader>
        </Card>
      </div>

      <div v-if="!loading && games.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Игр пока нет. Добавьте первую игру.
      </div>
    </div>

    <DialogRoot v-model:open="deleteDialogOpen" @update:open="(v: boolean) => !v && closeDeleteDialog()">
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">Удалить игру?</DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Игра «{{ gameToDelete?.name }}» и все связанные данные будут удалены безвозвратно.
          </DialogDescription>
          <div class="flex justify-end gap-2 pt-4">
            <Button variant="outline" @click="deleteDialogOpen = false">Отмена</Button>
            <Button
              variant="destructive"
              :disabled="deletingGameId === gameToDelete?.id"
              @click="confirmDeleteGame()"
            >
              {{ deletingGameId === gameToDelete?.id ? 'Удаление...' : 'Удалить' }}
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
    </DialogRoot>

    <Sheet v-model:open="sheetOpen" side="right" class="w-full max-w-sm">
      <template #trigger>
        <span />
      </template>
      <div class="space-y-6 pt-6">
        <h3 class="text-lg font-semibold">
          Добавить локализацию{{ selectedGame ? ` — ${selectedGame.name}` : '' }}
        </h3>
        <form class="space-y-4" @submit.prevent="submitLocalization">
          <div class="space-y-2">
            <Label for="loc-code">Код (например ru, en)</Label>
            <Input id="loc-code" v-model="locCode" placeholder="ru" required maxlength="16" />
          </div>
          <div class="space-y-2">
            <Label for="loc-name">Название</Label>
            <Input id="loc-name" v-model="locName" placeholder="Русский" required />
          </div>
          <div v-if="locError" class="text-sm text-destructive">{{ locError }}</div>
          <div class="flex gap-2">
            <Button type="submit" :disabled="locSubmitting">
              {{ locSubmitting ? 'Сохранение...' : 'Добавить' }}
            </Button>
            <Button type="button" variant="outline" @click="sheetOpen = false">Отмена</Button>
          </div>
        </form>
      </div>
    </Sheet>
  </div>
</template>
