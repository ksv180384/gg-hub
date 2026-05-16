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

function typeLabel(status: string) {
  if (status === 'invitation' || status === 'revoked') return 'Приглашение';
  return 'Заявка';
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
  <div class="space-y-4">
        <h1 class="mb-4 text-xl font-semibold tracking-tight">Мои заявки и приглашения</h1>

        <div v-if="loading" class="flex justify-center py-10">
          <Spinner class="h-8 w-8" />
        </div>

        <template v-else-if="error">
          <p class="text-sm text-destructive">{{ error }}</p>
        </template>

        <template v-else>
          <p v-if="applications.length === 0" class="text-sm text-muted-foreground">
            Вы ещё не подавали заявки в гильдии.
          </p>
          <ul v-else class="space-y-2">
            <li
              v-for="app in applications"
              :key="app.id"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 hover:bg-muted/50 cursor-pointer"
              @click="openApplication(app)"
            >
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="min-w-0 truncate text-sm font-medium">
                    {{ app.guild?.name ?? 'Гильдия #' + app.guild_id }}
                  </p>
                  <span
                    class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-foreground"
                  >
                    {{ typeLabel(app.status) }}
                  </span>
                </div>
                <p class="text-xs text-muted-foreground">
                  Персонаж: {{ app.character?.name ?? '—' }}
                </p>
              </div>
              <div class="text-right text-xs">
                <p
                  :class="
                    app.status === 'pending' || app.status === 'invitation'
                      ? 'font-medium text-green-600 dark:text-green-400'
                      : 'text-muted-foreground'
                  "
                >
                  {{ statusLabel(app.status) }}
                </p>
                <p v-if="app.created_at" class="text-muted-foreground">
                  {{ new Date(app.created_at).toLocaleDateString('ru-RU') }}
                </p>
              </div>
            </li>
          </ul>
        </template>
  </div>
</template>

