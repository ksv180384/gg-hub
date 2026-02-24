<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Spinner } from '@/shared/ui';
import { guildsApi, type GuildApplicationItem } from '@/shared/api/guildsApi';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const applicationId = computed(() => Number(route.params.applicationId));

const application = ref<GuildApplicationItem | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const characterName = computed(() => application.value?.character?.name ?? '—');
const characterGameClasses = computed(() => {
  const classes = application.value?.character?.game_classes;
  if (!classes?.length) return null;
  return classes.map((c) => c.name).join(', ');
});
const statusLabel = computed(() => {
  const s = application.value?.status;
  if (s === 'pending') return 'На рассмотрении';
  if (s === 'approved') return 'Принята';
  if (s === 'rejected') return 'Отклонена';
  return s ?? '—';
});

function getFieldLabel(fieldId: number | string): string {
  const labels = application.value?.form_field_labels;
  if (!labels) return `Поле ${fieldId}`;
  return labels[fieldId] ?? labels[String(fieldId)] ?? `Поле ${fieldId}`;
}

function isImageUrl(val: unknown): boolean {
  if (val == null) return false;
  const s = String(val).trim();
  return s.length > 0 && /^https?:\/\//i.test(s);
}

onMounted(async () => {
  if (!guildId.value || !applicationId.value) {
    error.value = 'Неверная ссылка.';
    loading.value = false;
    return;
  }
  try {
    application.value = await guildsApi.getMyGuildApplication(guildId.value, applicationId.value);
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) error.value = 'Заявка не найдена.';
    else if (err.status === 401) {
      router.push({ name: 'login', query: { redirect: route.fullPath } });
      return;
    } else {
      error.value = err.message ?? 'Не удалось загрузить заявку.';
    }
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="container py-6">
    <template v-if="loading">
      <Card class="max-w-2xl mx-auto">
        <CardContent class="flex items-center justify-center py-12">
          <Spinner class="h-8 w-8" />
        </CardContent>
      </Card>
    </template>

    <template v-else-if="application">
      <Card class="max-w-2xl mx-auto">
        <CardHeader class="flex flex-row items-end justify-between gap-4 flex-wrap">
          <div>
            <CardTitle class="text-xl">Ваша заявка в гильдию</CardTitle>
            <p class="mt-1 text-sm text-muted-foreground">
              Персонаж: {{ characterName }}
              <template v-if="characterGameClasses">
                · {{ characterGameClasses }}
              </template>
            </p>
            <p class="mt-0.5 text-sm text-muted-foreground">
              Статус: {{ statusLabel }}
              <template v-if="application.created_at">
                · {{ new Date(application.created_at).toLocaleDateString('ru-RU') }}
              </template>
            </p>
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <div v-if="application.form_data && Object.keys(application.form_data).length > 0" class="space-y-3">
            <h3 class="text-sm font-medium text-muted-foreground">Ответы на вопросы формы</h3>
            <dl class="space-y-2">
              <div
                v-for="(value, fieldId) in application.form_data"
                :key="fieldId"
                class="flex flex-col gap-0.5 sm:flex-row sm:gap-2"
              >
                <dt class="text-sm font-medium text-muted-foreground sm:w-48 shrink-0">{{ getFieldLabel(fieldId) }}</dt>
                <dd class="text-sm break-words">
                  <template v-if="value && isImageUrl(value)">
                    <img
                      :src="value"
                      :alt="getFieldLabel(fieldId)"
                      class="max-w-[320px] w-full rounded border object-cover"
                    >
                  </template>
                  <template v-else>{{ value || '—' }}</template>
                </dd>
              </div>
            </dl>
          </div>
        </CardContent>
      </Card>
    </template>

    <template v-else>
      <Card class="max-w-2xl mx-auto">
        <CardContent class="py-8 text-center">
          <p class="text-muted-foreground">{{ error ?? 'Заявка не найдена.' }}</p>
          <Button
            variant="outline"
            class="mt-4"
            @click="router.push({ name: 'guilds' })"
          >
            К списку гильдий
          </Button>
        </CardContent>
      </Card>
    </template>
  </div>
</template>

