<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Spinner } from '@/shared/ui';
import {
  constantPartiesApi,
  type ConstantParty,
  type ConstantPartyInvitation,
} from '@/shared/api/constantPartiesApi';
import { useSiteContextStore } from '@/stores/siteContext';

const router = useRouter();
const siteContext = useSiteContextStore();
const parties = ref<ConstantParty[]>([]);
const invitations = ref<ConstantPartyInvitation[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const processingInvitationId = ref<number | null>(null);

async function load() {
  loading.value = true;
  error.value = null;
  try {
    if (!siteContext.game) {
      parties.value = [];
      invitations.value = [];
      error.value = 'Конст пати доступны только на сайте выбранной игры.';
      return;
    }

    const result = await constantPartiesApi.list(siteContext.game.id);
    parties.value = result.parties;
    invitations.value = result.invitations;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить конст пати.';
  } finally {
    loading.value = false;
  }
}

async function accept(invitation: ConstantPartyInvitation) {
  processingInvitationId.value = invitation.id;
  try {
    const updated = await constantPartiesApi.acceptInvitation(invitation.id);
    invitations.value = invitations.value.filter((item) => item.id !== invitation.id);
    await router.push({ name: 'constant-party-show', params: { id: String(updated.constant_party_id) } });
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось принять приглашение.';
  } finally {
    processingInvitationId.value = null;
  }
}

async function decline(invitation: ConstantPartyInvitation) {
  processingInvitationId.value = invitation.id;
  try {
    await constantPartiesApi.declineInvitation(invitation.id);
    invitations.value = invitations.value.filter((item) => item.id !== invitation.id);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отклонить приглашение.';
  } finally {
    processingInvitationId.value = null;
  }
}

onMounted(load);
</script>

<template>
  <div class="container">
    <div class="mx-auto max-w-4xl">
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold tracking-tight">Мои КП</h1>
          <p class="mt-1 text-sm text-muted-foreground">Конст пати ваших персонажей и входящие приглашения.</p>
        </div>
        <RouterLink
          v-if="siteContext.game"
          to="/my-constant-parties/create"
          class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
        >
          Создать КП
        </RouterLink>
      </div>

      <div v-if="loading" class="flex justify-center py-10">
        <Spinner class="h-8 w-8" />
      </div>

      <div v-else class="space-y-6">
        <div v-if="error" class="rounded-md border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          {{ error }}
        </div>

        <section v-if="invitations.length > 0" class="space-y-3">
          <h2 class="text-base font-semibold">Приглашения</h2>
          <div class="overflow-hidden rounded-lg border bg-background">
            <div v-for="invitation in invitations" :key="invitation.id" class="border-b p-4 last:border-b-0">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-medium">{{ invitation.constant_party?.name ?? 'Конст пати' }}</p>
                  <p class="mt-1 text-sm text-muted-foreground">
                    Персонаж: {{ invitation.invited_character?.name ?? '...' }}
                    <span v-if="invitation.invited_by_character"> · Пригласил: {{ invitation.invited_by_character.name }}</span>
                  </p>
                </div>
                <div class="flex gap-2">
                  <button
                    type="button"
                    class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground disabled:opacity-60"
                    :disabled="processingInvitationId === invitation.id"
                    @click="accept(invitation)"
                  >
                    Принять
                  </button>
                  <button
                    type="button"
                    class="h-9 rounded-md border px-3 text-sm font-medium disabled:opacity-60"
                    :disabled="processingInvitationId === invitation.id"
                    @click="decline(invitation)"
                  >
                    Отклонить
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="space-y-3">
          <h2 class="text-base font-semibold">Конст пати</h2>
          <div v-if="parties.length === 0" class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
            У вас пока нет конст пати.
          </div>
          <div v-else class="overflow-hidden rounded-lg border bg-background">
            <RouterLink
              v-for="party in parties"
              :key="party.id"
              :to="{ name: 'constant-party-show', params: { id: String(party.id) } }"
              class="flex items-center justify-between gap-3 border-b px-4 py-3 transition-colors hover:bg-muted/40 last:border-b-0"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ party.name }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                  {{ party.server?.name ?? 'Сервер' }} · {{ party.members_count ?? party.members?.length ?? 0 }} участников
                </p>
              </div>
              <span class="shrink-0 text-xs text-muted-foreground">
                {{ party.my_member?.role === 'leader' ? 'Лидер' : party.my_member?.can_manage_storage ? 'Хранилище' : 'Участник' }}
              </span>
            </RouterLink>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
