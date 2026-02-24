<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Spinner } from '@/shared/ui';
import { guildsApi, type GuildApplicationItem } from '@/shared/api/guildsApi';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));

const applications = ref<GuildApplicationItem[]>([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const loading = ref(true);
const error = ref<string | null>(null);
const noAccess = ref(false);

function statusLabel(status: string) {
  if (status === 'pending') return 'На рассмотрении';
  if (status === 'approved') return 'Принята';
  if (status === 'rejected') return 'Отклонена';
  return status;
}

function goToApplication(appId: number) {
  router.push({ name: 'guild-application-show', params: { id: String(guildId.value), applicationId: String(appId) } });
}

onMounted(async () => {
  if (!guildId.value || Number.isNaN(guildId.value)) {
    error.value = 'Неверная ссылка.';
    loading.value = false;
    return;
  }
  try {
    const result = await guildsApi.getGuildApplications(guildId.value, { page: 1, per_page: 20 });
    applications.value = result.applications;
    meta.value = result.meta;
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 403) {
      noAccess.value = true;
      error.value = null;
    } else if (err.status === 404) {
      error.value = 'Гильдия не найдена.';
    } else {
      error.value = err.message ?? 'Не удалось загрузить заявки.';
    }
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="container py-6">
    <Card class="max-w-2xl mx-auto">
      <CardHeader>
        <CardTitle class="text-xl">Заявки и приглашения</CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="loading" class="flex justify-center py-12">
          <Spinner class="h-8 w-8" />
        </div>

        <template v-else-if="noAccess">
          <p class="text-muted-foreground mb-4">
            Список заявок доступен только участникам гильдии с правом просмотра заявок.
          </p>
          <Button
            variant="outline"
            @click="router.push({ name: 'guild-application-form', params: { id: String(guildId) } })"
          >
            Подать заявку в гильдию
          </Button>
        </template>

        <template v-else-if="error">
          <p class="text-destructive">{{ error }}</p>
          <Button variant="outline" class="mt-4" @click="router.push({ name: 'guild-show', params: { id: String(guildId) } })">
            К гильдии
          </Button>
        </template>

        <template v-else>
          <p v-if="applications.length === 0" class="text-muted-foreground">
            Заявок пока нет.
          </p>
          <ul v-else class="space-y-2">
            <li
              v-for="app in applications"
              :key="app.id"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 hover:bg-muted/50 transition-colors"
            >
              <div class="min-w-0">
                <p class="font-medium">{{ app.character?.name ?? '—' }}</p>
                <p class="text-sm text-muted-foreground">
                  {{ statusLabel(app.status) }}
                  <template v-if="app.created_at">
                    · {{ new Date(app.created_at).toLocaleDateString('ru-RU') }}
                  </template>
                </p>
              </div>
              <Button variant="outline" size="sm" @click="goToApplication(app.id)">
                Открыть
              </Button>
            </li>
          </ul>
          <Button
            variant="outline"
            class="mt-4"
            @click="router.push({ name: 'guild-show', params: { id: String(guildId) } })"
          >
            К гильдии
          </Button>
        </template>
      </CardContent>
    </Card>
  </div>
</template>
