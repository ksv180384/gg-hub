<script setup lang="ts">
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, TooltipProvider } from '@/shared/ui';
import { gamesApi, type Game, type GameClass } from '@/shared/api/gamesApi';
import DeleteClassConfirmDialog from './DeleteClassConfirmDialog.vue';
import GameClassRow from './GameClassRow.vue';
import ClassCreateForm from './ClassCreateForm.vue';

const props = defineProps<{ game: Game }>();
const emit = defineEmits<{ (e: 'update:game', game: Game): void }>();

const editingClassId = ref<number | null>(null);
const classEditSubmitting = ref(false);
const deletingClassId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const classToDelete = ref<GameClass | null>(null);
const classSubmitting = ref(false);
const classError = ref<string | null>(null);
const createFormKey = ref(0);

function startEditClass(gc: GameClass) {
  editingClassId.value = gc.id;
}

function cancelEditClass() {
  editingClassId.value = null;
}

async function submitEditClass(payload: {
  name: string;
  name_ru: string | null;
  slug: string;
  image?: File;
  remove_image: boolean;
}) {
  const id = editingClassId.value;
  if (!props.game || id == null) return;
  classEditSubmitting.value = true;
  try {
    await gamesApi.updateGameClass(id, {
      name: payload.name,
      name_ru: payload.name_ru,
      slug: payload.slug || undefined,
      image: payload.image,
      remove_image: payload.remove_image,
    });
    const updated = await gamesApi.getGame(props.game.id);
    emit('update:game', updated);
    cancelEditClass();
  } finally {
    classEditSubmitting.value = false;
  }
}

function openDeleteDialog(gc: GameClass) {
  classToDelete.value = gc;
  deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
  if (deletingClassId.value === null) {
    classToDelete.value = null;
    deleteDialogOpen.value = false;
  }
}

async function confirmDeleteClass() {
  const gc = classToDelete.value;
  if (!props.game || !gc || deletingClassId.value !== null) return;
  deletingClassId.value = gc.id;
  try {
    await gamesApi.deleteGameClass(gc.id);
    const updated = await gamesApi.getGame(props.game.id);
    emit('update:game', updated);
    deleteDialogOpen.value = false;
    classToDelete.value = null;
  } finally {
    deletingClassId.value = null;
  }
}

async function onCreateClass(payload: { name: string; name_ru: string; slug: string; image?: File }) {
  if (!props.game || !payload.name.trim()) return;
  classSubmitting.value = true;
  classError.value = null;
  try {
    await gamesApi.createGameClass(props.game.id, {
      name: payload.name.trim(),
      name_ru: payload.name_ru.trim() || undefined,
      slug: payload.slug.trim() || undefined,
      image: payload.image,
    });
    const updated = await gamesApi.getGame(props.game.id);
    emit('update:game', updated);
    createFormKey.value++;
  } catch (e: unknown) {
    classError.value = e instanceof Error ? e.message : 'Ошибка добавления класса';
  } finally {
    classSubmitting.value = false;
  }
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Классы</CardTitle>
      <p class="text-sm text-muted-foreground">
        Классы персонажей (например: Воин, Маг). Название обязательно, слаг и изображение — по желанию.
      </p>
    </CardHeader>
    <CardContent class="space-y-4">
      <template v-if="game.game_classes?.length">
        <TooltipProvider>
          <ul class="space-y-2">
            <GameClassRow
              v-for="gc in game.game_classes"
              :key="gc.id"
              :gc="gc"
              :is-editing="editingClassId === gc.id"
              :saving="classEditSubmitting"
              :deleting="deletingClassId === gc.id"
              @edit="startEditClass(gc)"
              @save="submitEditClass"
              @cancel="cancelEditClass"
              @delete="openDeleteDialog(gc)"
            />
          </ul>
        </TooltipProvider>
      </template>
      <p v-else class="text-sm text-muted-foreground">Нет классов. Добавьте классы ниже.</p>

      <ClassCreateForm
        :key="createFormKey"
        :submitting="classSubmitting"
        :error="classError"
        @submit="onCreateClass"
      />
    </CardContent>
  </Card>

  <DeleteClassConfirmDialog
    :open="deleteDialogOpen"
    :class-to-delete="classToDelete"
    :loading="deletingClassId !== null"
    @update:open="(v) => { if (deletingClassId === null) { deleteDialogOpen = v; if (!v) classToDelete = null; } }"
    @confirm="confirmDeleteClass"
  />
</template>
