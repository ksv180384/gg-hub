<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import CharacterForm from '../CharacterForm.vue';

const router = useRouter();
const siteContext = useSiteContextStore();
const game = computed(() => siteContext.game);

const gameFull = ref<Game | null>(null);
const gameLoading = ref(false);

async function loadGame() {
  if (!game.value?.id) return;
  gameLoading.value = true;
  try {
    gameFull.value = await gamesApi.getGame(game.value.id);
  } finally {
    gameLoading.value = false;
  }
}

function onSaved() {
  router.push({ name: 'characters' });
}

function onCancel() {
  router.push({ name: 'characters' });
}

onMounted(() => {
  loadGame();
});
</script>

<template>
  <div class="container py-6">
    <Card v-if="!game" class="mx-auto max-w-xl border-destructive/50">
      <CardHeader>
        <CardTitle>Новый персонаж</CardTitle>
      </CardHeader>
      <CardContent>
        <p class="text-sm text-muted-foreground">
          Перейдите на страницу игры (поддомен игры), чтобы создавать персонажей.
        </p>
        <Button class="mt-4" variant="outline" @click="router.push({ name: 'characters' })">
          К списку персонажей
        </Button>
      </CardContent>
    </Card>

    <template v-else>
      <div class="mx-auto max-w-xl">
        <div class="mb-6">
          <Button variant="ghost" size="sm" class="shrink-0 -ml-2" @click="router.push({ name: 'characters' })">
            ← К списку
          </Button>
        </div>
        <Card>
        <CardHeader>
          <CardTitle>Новый персонаж</CardTitle>
          <p class="text-sm text-muted-foreground">{{ game.name }}</p>
        </CardHeader>
        <CardContent>
          <div v-if="gameLoading" class="text-sm text-muted-foreground">Загрузка…</div>
          <CharacterForm
            v-else
            :game-full="gameFull"
            :game-loading="gameLoading"
            :editing-character="null"
            :game-id="game.id"
            @saved="onSaved"
            @cancel="onCancel"
          />
        </CardContent>
        </Card>
      </div>
    </template>
  </div>
</template>
