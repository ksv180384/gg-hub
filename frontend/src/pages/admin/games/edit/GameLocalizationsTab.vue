<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Input, Label, Badge } from '@/shared/ui';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import { ref } from 'vue';

const props = defineProps<{ game: Game }>();
const emit = defineEmits<{ (e: 'update:game', game: Game): void }>();

const locCode = ref('');
const locName = ref('');
const locSubmitting = ref(false);
const locError = ref<string | null>(null);

async function submitLocalization() {
  if (!props.game) return;
  locSubmitting.value = true;
  locError.value = null;
  try {
    await gamesApi.createLocalization(props.game.id, {
      code: locCode.value.trim(),
      name: locName.value.trim(),
    });
    locCode.value = '';
    locName.value = '';
    const updated = await gamesApi.getGame(props.game.id);
    emit('update:game', updated);
  } catch (e: unknown) {
    const err = e as Error & { errors?: Record<string, string[]> };
    locError.value =
      err.errors?.code?.[0] ?? err.errors?.name?.[0] ?? err.message ?? 'Ошибка добавления локализации';
  } finally {
    locSubmitting.value = false;
  }
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Локализации</CardTitle>
      <p class="text-sm text-muted-foreground">Языковые/региональные версии игры (например: RU, EU).</p>
    </CardHeader>
    <CardContent class="space-y-4">
      <div v-if="game.localizations?.length" class="flex flex-wrap gap-2">
        <Badge v-for="loc in game.localizations" :key="loc.id" variant="secondary">
          {{ loc.code }}: {{ loc.name }}
        </Badge>
      </div>
      <p v-else class="text-sm text-muted-foreground">Нет локализаций.</p>
      <form class="flex flex-wrap items-end gap-3 border-t pt-4" @submit.prevent="submitLocalization">
        <div class="space-y-1">
          <Label for="edit-loc-code" class="text-xs">Код</Label>
          <Input id="edit-loc-code" v-model="locCode" placeholder="ru" maxlength="16" class="w-24" />
        </div>
        <div class="space-y-1">
          <Label for="edit-loc-name" class="text-xs">Название</Label>
          <Input id="edit-loc-name" v-model="locName" placeholder="Русский" class="w-40" />
        </div>
        <Button type="submit" size="sm" :disabled="locSubmitting || !locCode.trim() || !locName.trim()">
          {{ locSubmitting ? 'Сохранение...' : 'Добавить' }}
        </Button>
        <p v-if="locError" class="w-full text-sm text-destructive">{{ locError }}</p>
      </form>
    </CardContent>
  </Card>
</template>
