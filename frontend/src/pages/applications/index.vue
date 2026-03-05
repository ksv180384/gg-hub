<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Spinner } from '@/shared/ui';
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

function openApplication(app: GuildApplicationItem) {
  if (!app.guild_id || !app.id) return;
  router.push({
    name: 'guild-application-my',
    params: { id: String(app.guild_id), applicationId: String(app.id) },
  });
}
</script>

<template>
  <div class="container py-6">
    <Card class="max-w-3xl mx-auto">
      <CardHeader>
        <CardTitle class="text-xl">Мои заявки и приглашения</CardTitle>
      </CardHeader>
      <CardContent>
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
                <p class="text-sm font-medium">
                  {{ app.guild?.name ?? 'Гильдия #' + app.guild_id }}
                </p>
                <p class="text-xs text-muted-foreground">
                  Персонаж: {{ app.character?.name ?? '—' }}
                </p>
              </div>
              <div class="text-right text-xs text-muted-foreground">
                <p>{{ statusLabel(app.status) }}</p>
                <p v-if="app.created_at">
                  {{ new Date(app.created_at).toLocaleDateString('ru-RU') }}
                </p>
              </div>
            </li>
          </ul>
        </template>
      </CardContent>
    </Card>
  </div>
</template>

