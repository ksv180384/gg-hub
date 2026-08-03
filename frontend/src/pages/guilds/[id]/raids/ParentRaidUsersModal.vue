<script setup lang="ts">
import { Button, Badge } from '@/shared/ui';
import type { RaidDescendantUser } from '@/shared/api/guildsApi';

defineProps<{
  open: boolean;
  raidName: string;
  users: RaidDescendantUser[];
  loading?: boolean;
}>();

const emit = defineEmits<{
  (event: 'close'): void;
}>();
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
      <button
        type="button"
        class="absolute inset-0 bg-black/75"
        aria-label="Закрыть"
        @click="emit('close')"
      />
      <section class="relative z-10 flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border bg-background shadow-xl">
        <header class="flex items-start justify-between gap-3 border-b p-5">
          <div>
            <h2 class="text-lg font-semibold">Участники «{{ raidName }}»</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Пользователи из всех дочерних рейдов и их принадлежность к составам.
            </p>
          </div>
          <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="emit('close')">
            ×
          </Button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
          <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="users.length === 0" class="text-sm text-muted-foreground">
            В дочерних рейдах пока нет участников.
          </p>
          <div v-else class="space-y-4">
            <article v-for="user in users" :key="user.user_id" class="rounded-xl border p-4">
              <h3 class="font-semibold">{{ user.user_name || `Пользователь #${user.user_id}` }}</h3>
              <div class="mt-3 space-y-3">
                <div v-for="character in user.characters" :key="character.id">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ character.name }}</span>
                    <Badge v-for="gameClass in character.game_classes" :key="gameClass.id" variant="secondary">
                      {{ gameClass.name_ru || gameClass.name }}
                    </Badge>
                    <Badge v-for="tag in character.tags" :key="tag.id" variant="outline">
                      {{ tag.name }}
                    </Badge>
                  </div>
                  <div class="mt-2 flex flex-wrap gap-1.5">
                    <Badge v-for="raid in character.raids" :key="raid.id">
                      {{ raid.name }}
                    </Badge>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>
    </div>
  </Teleport>
</template>
