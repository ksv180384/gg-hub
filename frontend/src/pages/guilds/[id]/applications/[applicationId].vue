<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
const actionLoading = ref<'approve' | 'reject' | null>(null);
const canReview = ref(false);
const fullSizeImageUrl = ref<string | null>(null);

/** Считаем значение ссылкой на изображение, если это строка, начинающаяся с http(s):// (для скриншотов и картинок). */
function isImageUrl(val: unknown): boolean {
  if (val == null) return false;
  const s = String(val).trim();
  return s.length > 0 && /^https?:\/\//i.test(s);
}

function openFullSize(url: string) {
  fullSizeImageUrl.value = url;
}

function closeFullSize() {
  fullSizeImageUrl.value = null;
}

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') closeFullSize();
}

const characterName = computed(() => application.value?.character?.name ?? '—');
const characterGameClasses = computed(() => {
  const classes = application.value?.character?.game_classes;
  if (!classes?.length) return null;
  return classes.map((c) => c.name).join(', ');
});
const statusLabel = computed(() => {
  const s = application.value?.status;
  if (s === 'pending') return 'На рассмотрении';
  if (s === 'invitation') return 'Приглашение (ожидает ответа)';
  if (s === 'approved') return 'Принята';
  if (s === 'rejected') return 'Отклонена';
  return s ?? '—';
});
const isInvitation = computed(() => application.value?.status === 'invitation');
const inviterName = computed(() => application.value?.invited_by_character?.name ?? null);

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

onMounted(async () => {
  if (!guildId.value || !applicationId.value) {
    error.value = 'Неверная ссылка.';
    loading.value = false;
    return;
  }
  try {
    const [app, settings] = await Promise.all([
      guildsApi.getGuildApplication(guildId.value, applicationId.value),
      guildsApi.getGuildForSettings(guildId.value).catch(() => null),
    ]);
    application.value = app;
    canReview.value = settings?.my_permission_slugs?.includes('podtverzdenie-ili-otklonenie-zaiavok') ?? false;
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 403) {
      router.replace({ name: 'guild-applications', params: { id: String(guildId.value) } });
      return;
    }
    if (err.status === 404) error.value = 'Заявка не найдена.';
    else error.value = err.message ?? 'Не удалось загрузить заявку.';
  } finally {
    loading.value = false;
  }
  document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', onEscape);
});

async function approve() {
  if (!application.value || actionLoading.value) return;
  actionLoading.value = 'approve';
  try {
    application.value = await guildsApi.approveGuildApplication(guildId.value, applicationId.value);
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Ошибка принятия заявки.';
  } finally {
    actionLoading.value = null;
  }
}

async function reject() {
  if (!application.value || actionLoading.value) return;
  actionLoading.value = 'reject';
  try {
    application.value = await guildsApi.rejectGuildApplication(guildId.value, applicationId.value);
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Ошибка отклонения заявки.';
  } finally {
    actionLoading.value = null;
  }
}
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
              {{ isInvitation ? 'Приглашение: ' : 'Заявка: ' }}{{ characterName }}
            </CardTitle>
            <p v-if="isInvitation && inviterName" class="mt-0.5 text-sm text-muted-foreground">
              Приглашение отправил(а): {{ inviterName }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
              {{ statusLabel }}
              <template v-if="application.created_at">
                · {{ new Date(application.created_at).toLocaleDateString('ru-RU') }}
              </template>
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            @click="router.push({ name: 'guild-applications', params: { id: String(guildId) } })"
          >
            К списку заявок
          </Button>
        </CardHeader>
        <CardContent class="space-y-6">
          <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

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
                      class="max-w-[320px] w-full cursor-pointer rounded border object-cover hover:opacity-90 transition-opacity"
                      @click="openFullSize(value)"
                    >
                  </template>
                  <template v-else>{{ formatFormFieldValue(value) }}</template>
                </dd>
              </div>
            </dl>
          </div>

          <div
            v-if="canReview && application.status === 'pending'"
            class="flex flex-wrap gap-3 pt-2"
          >
            <Button
              :disabled="!!actionLoading"
              @click="approve"
            >
              <Spinner v-if="actionLoading === 'approve'" class="mr-2 h-4 w-4" />
              Принять заявку
            </Button>
            <Button
              variant="destructive"
              :disabled="!!actionLoading"
              @click="reject"
            >
              <Spinner v-if="actionLoading === 'reject'" class="mr-2 h-4 w-4" />
              Отклонить
            </Button>
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
            @click="router.push({ name: 'guild-applications', params: { id: String(guildId) } })"
          >
            К списку заявок
          </Button>
        </CardContent>
      </Card>
    </template>

    <!-- Lightbox: полноразмерное изображение по клику -->
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
