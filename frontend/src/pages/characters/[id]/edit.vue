<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import CharacterForm from '../CharacterForm.vue';

const route = useRoute();
const router = useRouter();
const characterId = computed(() => Number(route.params.id));

const character = ref<Character | null>(null);
const gameFull = ref<Game | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

async function loadCharacter() {
  const id = characterId.value;
  if (!id || Number.isNaN(id)) {
    router.replace({ name: 'characters' });
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    character.value = await charactersApi.getCharacter(id);
    gameFull.value = await gamesApi.getGame(character.value.game_id);
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) {
      router.replace({ name: 'characters' });
    } else {
      error.value = err.message ?? 'Не удалось загрузить персонажа';
    }
  } finally {
    loading.value = false;
  }
}

function onSaved() {
  router.push({ name: 'characters' });
}

function onCancel() {
  router.push({ name: 'characters' });
}

onMounted(() => loadCharacter());
watch(characterId, (id) => {
  if (id && !Number.isNaN(id)) loadCharacter();
});
</script>

<template>
  <div class="container py-6">
    <div class="mx-auto max-w-xl">
      <div class="mb-6">
        <Button variant="ghost" size="sm" class="shrink-0 -ml-2" @click="router.push({ name: 'characters' })">
          ← К списку
        </Button>
      </div>

      <Card v-if="error" class="border-destructive/50">
      <CardContent class="pt-6">
        <p class="text-sm text-destructive">{{ error }}</p>
        <Button class="mt-4" variant="outline" @click="router.push({ name: 'characters' })">
          К списку персонажей
        </Button>
      </CardContent>
      </Card>

      <Card v-else-if="loading">
        <CardContent class="py-8">
          <p class="text-sm text-muted-foreground">Загрузка…</p>
        </CardContent>
      </Card>

      <Card v-else-if="character">
      <CardHeader>
        <CardTitle>Редактирование персонажа</CardTitle>
        <p class="text-sm text-muted-foreground">{{ character.name }}</p>
      </CardHeader>
      <CardContent>
        <CharacterForm
          :game-full="gameFull"
          :game-loading="false"
          :editing-character="character"
          :game-id="character.game_id"
          @saved="onSaved"
          @cancel="onCancel"
        />
      </CardContent>
      </Card>
    </div>
  </div>
</template>
