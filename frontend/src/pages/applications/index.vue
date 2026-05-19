<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Spinner } from '@/shared/ui';
import { guildsApi, type GuildApplicationItem } from '@/shared/api/guildsApi';

const router = useRouter();

const applications = ref<GuildApplicationItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  loading.value = true;
  error.value = null;
  try {
    const result = await guildsApi.getMyGuildApplicationsList({ page: 1, per_page: 50 });
    applications.value = result.applications;
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 401) {
      router.push({ name: 'login', query: { redirect: '/applications' } });
      return;
    }
    error.value = err.message ?? 'Не удалось загрузить ваши заявки.';
  } finally {
    loading.value = false;
  }
});

function statusLabel(status: string) {
  if (status === 'pending') return 'На рассмотрении';
  if (status === 'invitation') return 'Приглашение';
  if (status === 'approved') return 'Принята';
  if (status === 'rejected') return 'Отклонена';
  if (status === 'revoked') return 'Приглашение отозвано';
  if (status === 'withdrawn') return 'Отозвана';
  return status;
}

function statusClass(status: string) {
  if (status === 'pending' || status === 'invitation') {
    return 'text-primary';
  }
  if (status === 'approved') {
    return 'text-emerald-600';
  }
  if (status === 'rejected' || status === 'revoked' || status === 'withdrawn') {
    return 'text-muted-foreground';
  }
  return 'text-muted-foreground';
}

function typeLabel(status: string) {
  if (status === 'invitation' || status === 'revoked') return 'Приглашение';
  return 'Заявка';
}

function formatDate(value: string | null | undefined) {
  if (!value) return null;
  return new Date(value).toLocaleDateString('ru-RU');
}

function openApplication(app: GuildApplicationItem) {
  if (!app.guild_id || !app.id) return;
  router.push({
    name: 'guild-application-my',
    params: { id: String(app.guild_id), applicationId: String(app.id) },
  });
}
</script>

<template>
  <div class="container">
    <div class="mx-auto">
      <div class="mb-5">
        <h1 class="text-2xl font-bold tracking-tight">Мои заявки и приглашения</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          История заявок в гильдии, приглашений и решений по вступлению
        </p>
      </div>

      <div v-if="loading" class="flex justify-center py-10">
        <Spinner class="h-8 w-8" />
      </div>

      <template v-else-if="error">
        <div class="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
          {{ error }}
        </div>
      </template>

      <template v-else>
        <div
          v-if="applications.length === 0"
          class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
        >
          Вы ещё не подавали заявки в гильдии.
        </div>
        <ul v-else class="overflow-hidden rounded-lg border border-border bg-background shadow-sm">
          <li
            v-for="app in applications"
            :key="app.id"
            class="border-b border-border last:border-b-0"
          >
            <button
              type="button"
              class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
              @click="openApplication(app)"
            >
              <div class="min-w-0 flex-1">
                <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
                  <span class="min-w-0 truncate text-sm font-medium text-foreground">
                    {{ app.guild?.name ?? 'Гильдия #' + app.guild_id }}
                  </span>
                  <span class="text-xs text-muted-foreground">
                    {{ typeLabel(app.status) }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                  Персонаж:
                  <span class="font-medium text-foreground">{{ app.character?.name ?? '—' }}</span>
                  <span v-if="formatDate(app.created_at)"> · {{ formatDate(app.created_at) }}</span>
                </p>
              </div>
              <div class="flex shrink-0 items-center gap-3">
                <span
                  class="hidden text-right text-xs font-medium sm:inline"
                  :class="statusClass(app.status)"
                >
                  {{ statusLabel(app.status) }}
                </span>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m9 18 6-6-6-6" />
                  </svg>
                </span>
              </div>
            </button>
          </li>
        </ul>
      </template>
    </div>
  </div>
</template>
