<script setup lang="ts">
import { Button, Badge } from '@/shared/ui';
import type { RaidApplicationItem } from '@/shared/api/guildsApi';

defineProps<{
  open: boolean;
  raidName: string;
  applications: RaidApplicationItem[];
  loading?: boolean;
  decidingId?: number | null;
}>();

const emit = defineEmits<{
  (event: 'close'): void;
  (event: 'accept', applicationId: number): void;
  (event: 'reject', applicationId: number): void;
}>();
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <button
        type="button"
        class="absolute inset-0 bg-black/75"
        aria-label="Закрыть"
        @click="emit('close')"
      />
      <section class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl border bg-background shadow-xl">
        <header class="flex items-start justify-between gap-3 border-b p-5">
          <div>
            <h2 class="text-lg font-semibold">Заявки · {{ raidName }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">Новые заявки на вступление в рейд.</p>
          </div>
          <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="emit('close')">
            ×
          </Button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
          <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="applications.length === 0" class="text-sm text-muted-foreground">
            Новых заявок нет.
          </p>
          <div v-else class="space-y-3">
            <article
              v-for="application in applications"
              :key="application.id"
              class="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center"
            >
              <div class="min-w-0 flex-1">
                <div class="font-semibold">{{ application.character?.name }}</div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <Badge
                    v-for="gameClass in application.character?.game_classes || []"
                    :key="gameClass.id"
                    variant="secondary"
                  >
                    {{ gameClass.name_ru || gameClass.name }}
                  </Badge>
                  <Badge
                    v-for="tag in [...(application.character?.tags || []), ...(application.character?.personal_tags || [])]"
                    :key="`${tag.id}:${tag.slug}`"
                    variant="outline"
                  >
                    {{ tag.name }}
                  </Badge>
                </div>
              </div>
              <div class="flex shrink-0 gap-2">
                <Button
                  size="sm"
                  :disabled="decidingId != null"
                  @click="emit('accept', application.id)"
                >
                  Принять
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="decidingId != null"
                  @click="emit('reject', application.id)"
                >
                  Отклонить
                </Button>
              </div>
            </article>
          </div>
        </div>
      </section>
    </div>
  </Teleport>
</template>
