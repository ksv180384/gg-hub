<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Spinner } from '@/shared/ui';
import { guildsApi, type GuildApplicationItem } from '@/shared/api/guildsApi';
import ApplicationComments from '@/pages/guilds/[id]/applications/ApplicationComments.vue';
import CharacterClassBadge from '@/pages/characters/CharacterClassBadge.vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { ConfirmDialog } from '@/shared/ui/confirm-dialog';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const applicationId = computed(() => Number(route.params.applicationId));

const application = ref<GuildApplicationItem | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const characterName = computed(() => application.value?.character?.name ?? '—');
const characterGameClasses = computed(() => application.value?.character?.game_classes ?? []);
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
const withdrawDialogOpen = ref(false);
const actionError = ref<string | null>(null);

const fullSizeImageUrl = ref<string | null>(null);

function openFullSize(url: string) {
  fullSizeImageUrl.value = url;
}

function closeFullSize() {
  fullSizeImageUrl.value = null;
}

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') closeFullSize();
}

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

async function confirmWithdrawApplication() {
  if (!guildId.value || !applicationId.value || application.value?.status !== 'pending') return;
  withdrawing.value = true;
  actionError.value = null;
  try {
    const updated = await guildsApi.withdrawGuildApplication(guildId.value, applicationId.value);
    application.value = updated;
    withdrawDialogOpen.value = false;
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

/** Полноэкранный просмотр только для полей типа «скриншот»; если типы не пришли с API — любая http(s)-ссылка. */
function isScreenshotImageField(fieldId: number | string, value: unknown): boolean {
  if (!value || !isImageUrl(value)) return false;
  const types = application.value?.form_field_types;
  if (!types || Object.keys(types).length === 0) return true;
  const t = types[fieldId] ?? types[String(fieldId)];
  return t === 'screenshot';
}

function onScreenshotThumbClick(fieldId: number | string, value: unknown) {
  if (!isScreenshotImageField(fieldId, value) || value == null) return;
  openFullSize(String(value).trim());
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
  document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', onEscape);
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
            <p class="mt-1 text-sm text-muted-foreground">
              Персонаж:
              <span class="text-lg font-semibold text-foreground sm:text-xl">{{ characterName }}</span>
            </p>
            <div v-if="characterGameClasses.length" class="mt-1 flex flex-wrap gap-2">
              <CharacterClassBadge
                v-for="gc in characterGameClasses"
                :key="gc.id"
                :game-class="gc"
              />
            </div>
            <p class="mt-0.5 text-sm text-muted-foreground">
              Гильдия:
              <span class="font-semibold text-foreground">{{ guildName }}</span>
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
              variant="destructive"
              :disabled="withdrawing"
              @click="withdrawDialogOpen = true"
            >
              Отозвать заявку
            </Button>
          </div>

          <ConfirmDialog
            v-model:open="withdrawDialogOpen"
            title="Отозвать заявку?"
            confirm-label="Отозвать"
            cancel-label="Отмена"
            :loading="withdrawing"
            @confirm="confirmWithdrawApplication"
          >
            <template #description>
              <p>Заявка будет снята с рассмотрения. Восстановить её будет нельзя.</p>
            </template>
          </ConfirmDialog>
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
                      :class="
                        isScreenshotImageField(fieldId, value)
                          ? 'cursor-pointer transition-opacity hover:opacity-90'
                          : ''
                      "
                      role="presentation"
                      @click="onScreenshotThumbClick(fieldId, value)"
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

    <ClientOnly>
      <Teleport to="body">
        <Transition name="lightbox">
          <div
            v-if="fullSizeImageUrl"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
            aria-modal="true"
            role="dialog"
            aria-label="Просмотр изображения"
            @click.self="closeFullSize"
          >
            <button
              type="button"
              class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
              aria-label="Закрыть"
              @click="closeFullSize"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
            <img
              :src="fullSizeImageUrl"
              alt="Изображение в полном размере"
              class="max-h-[90vh] max-w-full select-none object-contain"
              @click.stop
            >
          </div>
        </Transition>
      </Teleport>
    </ClientOnly>
  </div>
</template>

<style scoped>
.lightbox-enter-active,
.lightbox-leave-active {
  transition: opacity 0.2s ease;
}
.lightbox-enter-from,
.lightbox-leave-to {
  opacity: 0;
}
.lightbox-enter-active img,
.lightbox-leave-active img {
  transition: transform 0.2s ease;
}
.lightbox-enter-from img,
.lightbox-leave-to img {
  transform: scale(0.95);
}
</style>

