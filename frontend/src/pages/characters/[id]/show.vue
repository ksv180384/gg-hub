<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { RouterLink } from 'vue-router';
import {
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Spinner,
} from '@/shared/ui';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import { useSiteContextStore } from '@/stores/siteContext';
import { useAuthStore } from '@/stores/auth';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { guildsApi, type UserGuildItem } from '@/shared/api/guildsApi';
import CharacterClassBadge from '../CharacterClassBadge.vue';

const route = useRoute();
const router = useRouter();
const siteContext = useSiteContextStore();
const auth = useAuthStore();
const game = computed(() => siteContext.game);

const character = ref<Character | null>(null);
const loading = ref(true);
const guildsWithInvite = ref<UserGuildItem[]>([]);
const inviteModalOpen = ref(false);
const selectedGuildId = ref<number | null>(null);
const inviteSending = ref(false);
const inviteError = ref<string | null>(null);
const inviteSuccess = ref(false);

const characterId = computed(() => Number(route.params.id));

async function loadCharacter() {
  if (!game.value?.id || !characterId.value) return;
  loading.value = true;
  try {
    character.value = await charactersApi.getGameCharacter(game.value.id, characterId.value);
  } catch {
    character.value = null;
  } finally {
    loading.value = false;
  }
}

async function loadGuildsWithInvite() {
  if (!auth.isAuthenticated || !game.value?.id) {
    guildsWithInvite.value = [];
    return;
  }
  try {
    const guilds = await guildsApi.getMyGuildsForGame(game.value.id);
    guildsWithInvite.value = guilds.filter((g) => g.can_invite);
  } catch {
    guildsWithInvite.value = [];
  }
}

const canShowInviteButton = computed(() => {
  if (!character.value || character.value.guild) return false;
  return guildsWithInvite.value.length > 0;
});

function openInviteModal() {
  inviteError.value = null;
  inviteSuccess.value = false;
  selectedGuildId.value =
    guildsWithInvite.value.length === 1 ? guildsWithInvite.value[0].id : null;
  inviteModalOpen.value = true;
}

function closeInviteModal() {
  inviteModalOpen.value = false;
  selectedGuildId.value = null;
  inviteError.value = null;
}

async function sendInvitation() {
  if (!character.value || selectedGuildId.value == null) return;
  inviteSending.value = true;
  inviteError.value = null;
  try {
    await guildsApi.sendGuildInvitation(selectedGuildId.value, character.value.id);
    inviteSuccess.value = true;
    setTimeout(() => {
      closeInviteModal();
    }, 1500);
  } catch (e) {
    inviteError.value = e instanceof Error ? e.message : 'Не удалось отправить приглашение';
  } finally {
    inviteSending.value = false;
  }
}

onMounted(() => {
  loadCharacter();
  loadGuildsWithInvite();
});
watch([() => game.value?.id, characterId], () => {
  loadCharacter();
  loadGuildsWithInvite();
});
</script>

<template>
  <div class="container py-6">
    <div v-if="loading" class="flex justify-center py-12">
      <Spinner class="h-8 w-8" />
    </div>

    <template v-else-if="!character">
      <Card class="border-destructive/50">
        <CardHeader>
          <CardTitle>Персонаж не найден</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-sm text-muted-foreground mb-4">
            Персонаж не найден или был удалён.
          </p>
          <Button variant="outline" @click="router.push({ name: 'game-characters' })">
            К списку персонажей
          </Button>
        </CardContent>
      </Card>
    </template>

    <template v-else>
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <Button
          variant="ghost"
          size="sm"
          class="shrink-0 -ml-2"
          @click="router.push({ name: 'game-characters' })"
        >
          ← К списку персонажей
        </Button>
      </div>

      <Card>
        <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex flex-wrap items-center gap-4">
            <Avatar
              :src="character.avatar_url ?? undefined"
              :alt="character.name"
              :fallback="character.name.slice(0, 2).toUpperCase()"
              class="h-20 w-20 shrink-0 rounded-lg"
            />
            <div class="min-w-0">
              <CardTitle class="text-xl">{{ character.name }}</CardTitle>
              <p class="text-sm text-muted-foreground mt-1">
                <span v-if="character.localization?.name">{{ character.localization.name }}</span>
                <template v-if="character.localization?.name && character.server?.name"> · </template>
                <span v-if="character.server?.name">{{ character.server.name }}</span>
                <template v-if="!character.localization?.name && !character.server?.name">—</template>
              </p>
              <div v-if="character.guild" class="mt-2">
                <RouterLink
                  :to="{ name: 'guild-show', params: { id: character.guild.id } }"
                  class="text-sm text-primary hover:underline"
                >
                  Гильдия: {{ character.guild.name }}
                </RouterLink>
              </div>
              <div v-if="character.game_classes?.length" class="mt-2 flex flex-wrap items-center gap-1.5">
                <CharacterClassBadge
                  v-for="gc in character.game_classes"
                  :key="gc.id"
                  :game-class="gc"
                />
              </div>
            </div>
          </div>
          <Button
            v-if="canShowInviteButton"
            class="shrink-0"
            @click="openInviteModal"
          >
            Пригласить в гильдию
          </Button>
        </CardHeader>
        <CardContent>
          <p v-if="!character.guild && !character.game_classes?.length && (!character.localization?.name && !character.server?.name)" class="text-sm text-muted-foreground">
            Дополнительная информация о персонаже отсутствует.
          </p>
        </CardContent>
      </Card>

      <!-- Модалка выбора гильдии для приглашения -->
      <DialogRoot :open="inviteModalOpen" @update:open="(v: boolean) => { if (!v) closeInviteModal(); }">
        <DialogPortal>
          <DialogOverlay
            class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
          />
          <DialogContent
            class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          >
            <DialogTitle class="text-lg font-semibold">
              {{ guildsWithInvite.length === 1 ? 'Отправить приглашение в гильдию' : 'Выберите гильдию для приглашения' }}
            </DialogTitle>
            <DialogDescription class="text-sm text-muted-foreground">
              Персонаж «{{ character?.name }}» получит приглашение в выбранную гильдию и уведомление.
            </DialogDescription>
            <div class="flex flex-col gap-3 pt-2">
              <button
                v-for="g in guildsWithInvite"
                :key="g.id"
                type="button"
                class="rounded-lg border p-3 text-left transition-colors hover:bg-muted/50"
                :class="{ 'border-primary bg-primary/5': selectedGuildId === g.id }"
                @click="selectedGuildId = g.id"
              >
                <span class="font-medium">{{ g.name }}</span>
              </button>
            </div>
            <p v-if="inviteError" class="text-sm text-destructive">{{ inviteError }}</p>
            <p v-if="inviteSuccess" class="text-sm text-green-600">Приглашение отправлено.</p>
            <div class="flex justify-end gap-2 pt-4">
              <Button variant="outline" :disabled="inviteSending" @click="closeInviteModal">
                Отмена
              </Button>
              <Button
                :disabled="selectedGuildId == null || inviteSending"
                @click="sendInvitation"
              >
                {{ inviteSending ? 'Отправка…' : 'Отправить приглашение' }}
              </Button>
            </div>
          </DialogContent>
        </DialogPortal>
      </DialogRoot>
    </template>
  </div>
</template>
