<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Spinner } from '@/shared/ui';
import { guildsApi, type GuildApplicationItem } from '@/shared/api/guildsApi';
import ApplicationComments from '@/pages/guilds/[id]/applications/ApplicationComments.vue';

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
const guildName = computed(() => application.value?.guild?.name ?? '—');

const statusLabel = computed(() => {
  const s = application.value?.status;
  if (s === 'pending') return 'На рассмотрении';
  if (s === 'invitation') return 'Приглашение';
  if (s === 'approved') return 'Принята';
  if (s === 'rejected') return 'Отклонена';
  if (s === 'revoked') return 'Приглашение отозвано';
  if (s === 'withdrawn') return 'Отозвана';
  return s ?? '—';
});

const isInvitation = computed(() => application.value?.status === 'invitation');
const inviterName = computed(() => application.value?.invited_by_character?.name ?? 'Участник гильдии');

const accepting = ref(false);
const declining = ref(false);
const withdrawing = ref(false);
const actionError = ref<string | null>(null);

async function acceptInvitation() {
  if (!guildId.value || !applicationId.value) return;
  accepting.value = true;
  actionError.value = null;
  try {
    await guildsApi.acceptGuildInvitation(guildId.value, applicationId.value);
    application.value = application.value
      ? { ...application.value, status: 'approved' as const }
      : null;
  } catch (e) {
    actionError.value = e instanceof Error ? e.message : 'Не удалось принять приглашение';
  } finally {
    accepting.value = false;
  }
}

async function declineInvitation() {
  if (!guildId.value || !applicationId.value) return;
  declining.value = true;
  actionError.value = null;
  try {
    await guildsApi.declineGuildInvitation(guildId.value, applicationId.value);
    application.value = application.value
      ? { ...application.value, status: 'rejected' as const }
      : null;
  } catch (e) {
    actionError.value = e instanceof Error ? e.message : 'Не удалось отклонить приглашение';
  } finally {
    declining.value = false;
  }
}

async function withdrawApplication() {
  if (!guildId.value || !applicationId.value || application.value?.status !== 'pending') return;
  withdrawing.value = true;
  actionError.value = null;
  try {
    const updated = await guildsApi.withdrawGuildApplication(guildId.value, applicationId.value);
    application.value = updated;
  } catch (e) {
    actionError.value = e instanceof Error ? e.message : 'Не удалось отозвать заявку';
  } finally {
    withdrawing.value = false;
  }
}

function getFieldLabel(fieldId: number | string): string {
  const labels = application.value?.form_field_labels;
  if (!labels) return `Поле ${fieldId}`;
  return labels[fieldId] ?? labels[String(fieldId)] ?? `Поле ${fieldId}`;
}

/** Для multiselect значение приходит как JSON-массив; выводим через запятую. */
function formatFormFieldValue(value: string): string {
  if (value == null || value === '') return '—';
  const trimmed = String(value).trim();
  if (trimmed.startsWith('[')) {
    try {
      const parsed = JSON.parse(trimmed) as unknown;
      if (Array.isArray(parsed)) {
        return parsed.map((x) => String(x)).join(', ') || '—';
      }
    } catch {
      /* fallback to raw */
    }
  }
  return trimmed || '—';
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
            <CardTitle class="text-xl">
              {{ isInvitation ? 'Приглашение в гильдию' : 'Ваша заявка в гильдию' }}
            </CardTitle>
            <p class="mt-1 text-sm font-medium text-foreground">
              Гильдия: {{ guildName }}
            </p>
            <p class="mt-0.5 text-sm text-muted-foreground">
              Персонаж: {{ characterName }}
              <template v-if="characterGameClasses">
                · {{ characterGameClasses }}
              </template>
            </p>
            <p v-if="isInvitation" class="mt-0.5 text-sm text-muted-foreground">
              Вас пригласил(а): {{ inviterName }}
            </p>
            <p v-if="application.status === 'revoked' && application.revoked_by_character?.name" class="mt-0.5 text-sm text-muted-foreground">
              Отозвал(а): {{ application.revoked_by_character.name }}
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
          <div v-if="isInvitation && (application.status === 'invitation')" class="flex flex-wrap gap-2">
            <Button :disabled="accepting || declining" @click="acceptInvitation">
              {{ accepting ? '…' : 'Принять приглашение' }}
            </Button>
            <Button variant="outline" :disabled="accepting || declining" @click="declineInvitation">
              {{ declining ? '…' : 'Отклонить' }}
            </Button>
          </div>
          <div v-else-if="application.status === 'pending'" class="flex flex-wrap gap-2">
            <Button
              variant="outline"
              :disabled="withdrawing"
              @click="withdrawApplication"
            >
              {{ withdrawing ? '…' : 'Отозвать заявку' }}
            </Button>
          </div>
          <p v-if="actionError" class="text-sm text-destructive">{{ actionError }}</p>
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
                  <template v-else>{{ formatFormFieldValue(value) }}</template>
                </dd>
              </div>
            </dl>
          </div>

          <ApplicationComments :guild-id="guildId" :application-id="applicationId" />
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

