<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Spinner } from '@/shared/ui';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { constantPartiesApi } from '@/shared/api/constantPartiesApi';

const router = useRouter();
const characters = ref<Character[]>([]);
const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const name = ref('');
const leaderCharacterId = ref<number | null>(null);

const canSubmit = computed(() => name.value.trim().length > 0 && leaderCharacterId.value !== null && !saving.value);

onMounted(async () => {
  loading.value = true;
  try {
    characters.value = await charactersApi.getCharacters();
    leaderCharacterId.value = characters.value[0]?.id ?? null;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить персонажей.';
  } finally {
    loading.value = false;
  }
});

async function submit() {
  if (!canSubmit.value || leaderCharacterId.value === null) return;
  saving.value = true;
  error.value = null;
  try {
    const party = await constantPartiesApi.create({
      name: name.value.trim(),
      leader_character_id: leaderCharacterId.value,
    });
    await router.push({ name: 'constant-party-show', params: { id: String(party.id) } });
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось создать конст пати.';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="container">
    <div class="mx-auto max-w-2xl">
      <div class="mb-5">
        <h1 class="text-2xl font-bold tracking-tight">Новая КП</h1>
        <p class="mt-1 text-sm text-muted-foreground">Выбранный персонаж станет лидером конст пати.</p>
      </div>

      <div v-if="loading" class="flex justify-center py-10">
        <Spinner class="h-8 w-8" />
      </div>

      <form v-else class="space-y-4 rounded-lg border bg-background p-4" @submit.prevent="submit">
        <div v-if="error" class="rounded-md border border-destructive/20 bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {{ error }}
        </div>

        <label class="block space-y-1">
          <span class="text-sm font-medium">Название</span>
          <input v-model="name" class="h-10 w-full rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring" maxlength="255" />
        </label>

        <label class="block space-y-1">
          <span class="text-sm font-medium">Лидер</span>
          <select v-model.number="leaderCharacterId" class="h-10 w-full rounded-md border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring">
            <option v-for="character in characters" :key="character.id" :value="character.id">
              {{ character.name }} · {{ character.server?.name ?? 'сервер' }}
            </option>
          </select>
        </label>

        <div v-if="characters.length === 0" class="rounded-md border border-dashed px-3 py-4 text-sm text-muted-foreground">
          Сначала создайте персонажа.
        </div>

        <div class="flex justify-end gap-2">
          <button type="button" class="h-9 rounded-md border px-4 text-sm font-medium" @click="router.push('/my-constant-parties')">
            Отмена
          </button>
          <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-60" :disabled="!canSubmit">
            Создать
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
