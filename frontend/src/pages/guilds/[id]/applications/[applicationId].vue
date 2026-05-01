<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Card, CardContent, CardHeader, CardTitle, Button, Spinner } from '@/shared/ui';
import { guildsApi, type GuildApplicationItem } from '@/shared/api/guildsApi';
import NotFoundPage from '@/pages/not-found/index.vue';
import ApplicationComments from '@/pages/guilds/[id]/applications/ApplicationComments.vue';
import CharacterClassBadge from '@/pages/characters/CharacterClassBadge.vue';

const route = useRoute();
const router = useRouter();
const guildId = computed(() => Number(route.params.id));
const applicationId = computed(() => Number(route.params.applicationId));

const application = ref<GuildApplicationItem | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
/** 404 с заявки / middleware (нет членства и т.п.), URL не меняем. */
const guildApplicationDetailNotFound = ref(false);
const actionLoading = ref<'approve' | 'reject' | 'revoke' | null>(null);
const voteLoading = ref<false | 'like' | 'dislike'>(false);
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
const characterGameClasses = computed(() => application.value?.character?.game_classes ?? []);
const statusLabel = computed(() => {
  const s = application.value?.status;
  if (s === 'pending') return 'На рассмотрении';
  if (s === 'invitation') return 'Приглашение (ожидает ответа)';
  if (s === 'approved') return 'Принята';
  if (s === 'rejected') return 'Отклонена';
  if (s === 'revoked') return 'Приглашение отозвано';
  if (s === 'withdrawn') return 'Отозвана';
  return s ?? '—';
});
const isInvitation = computed(() => application.value?.status === 'invitation');
const inviterName = computed(() => application.value?.invited_by_character?.name ?? null);
const revokerName = computed(() => application.value?.revoked_by_character?.name ?? null);
const likesCount = computed(() => application.value?.likes_count ?? 0);
const dislikesCount = computed(() => application.value?.dislikes_count ?? 0);
const myVote = computed(() => application.value?.my_vote ?? null);

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
  guildApplicationDetailNotFound.value = false;
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
    if (err.status === 404) {
      guildApplicationDetailNotFound.value = true;
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

async function revokeInvitation() {
  if (!application.value || actionLoading.value) return;
  actionLoading.value = 'revoke';
  try {
    application.value = await guildsApi.revokeGuildInvitation(guildId.value, applicationId.value);
    error.value = null;
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Ошибка отзыва приглашения.';
  } finally {
    actionLoading.value = null;
  }
}

async function setVote(vote: 'like' | 'dislike') {
  if (!application.value || voteLoading.value) return;
  voteLoading.value = vote;
  try {
    if (myVote.value === vote) {
      application.value = await guildsApi.removeGuildApplicationVote(guildId.value, applicationId.value);
    } else {
      application.value = await guildsApi.voteGuildApplication(guildId.value, applicationId.value, vote);
    }
    error.value = null;
  } catch (e: unknown) {
    error.value = (e as Error).message ?? 'Ошибка голосования по заявке.';
  } finally {
    voteLoading.value = false;
  }
}
</script>

<template>
  <NotFoundPage v-if="guildApplicationDetailNotFound" />
  <div v-else class="container py-6">
    <template v-if="loading">
      <Card class="max-w-2xl mx-auto">
        <CardContent class="flex items-center justify-center py-12">
          <Spinner class="h-8 w-8" />
        </CardContent>
      </Card>
    </template>

    <template v-else-if="application">
      <Card class="max-w-2xl mx-auto">
        <CardHeader class="flex flex-row items-start justify-between gap-4 flex-wrap">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <Button
                variant="ghost"
                size="icon"
                class="shrink-0"
                aria-label="К списку заявок"
                @click="router.push({ name: 'guild-applications', params: { id: String(guildId) } })"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M15 18l-6-6 6-6" />
                </svg>
              </Button>
              <CardTitle class="text-xl min-w-0 truncate">
                {{ isInvitation ? 'Приглашение: ' : 'Заявка: ' }}{{ characterName }}
              </CardTitle>
            </div>
            <div v-if="characterGameClasses.length" class="mt-1 flex flex-wrap gap-2">
              <CharacterClassBadge
                v-for="gc in characterGameClasses"
                :key="gc.id"
                :game-class="gc"
              />
            </div>
            <p v-if="isInvitation && inviterName" class="mt-0.5 text-sm text-muted-foreground">
              Приглашение отправил(а): {{ inviterName }}
            </p>
            <p v-if="application.status === 'revoked' && revokerName" class="mt-0.5 text-sm text-muted-foreground">
              Отозвал(а): {{ revokerName }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
              {{ statusLabel }}
              <template v-if="application.created_at">
                · {{ new Date(application.created_at).toLocaleDateString('ru-RU') }}
              </template>
            </p>
          </div>
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

          <div class="space-y-2 border-t pt-4">
            <div class="flex flex-wrap items-center gap-2">
              <Button
                variant="ghost"
                :disabled="!!voteLoading"
                size="icon"
                aria-label="Поставить лайк"
                :aria-pressed="myVote === 'like'"
                @click="setVote('like')"
              >
                <Spinner v-if="voteLoading === 'like'" class="h-4 w-4" />
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  :class="myVote === 'like' ? 'text-foreground' : 'text-muted-foreground'"
                  viewBox="0 0 24 24"
                  :fill="myVote === 'like' ? 'currentColor' : 'none'"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M7 10v12" />
                  <path d="M15 5.88 14 10h5.83a2 2 0 0 1 2 2.32l-1 7A2 2 0 0 1 18.85 21H7a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.94-1.51L12.83 2A1.65 1.65 0 0 1 16 2.4a10 10 0 0 1-1 3.48Z" />
                </svg>
              </Button>
              <span class="text-sm text-muted-foreground">{{ likesCount }}</span>
              <Button
                variant="ghost"
                :disabled="!!voteLoading"
                size="icon"
                aria-label="Поставить дизлайк"
                :aria-pressed="myVote === 'dislike'"
                @click="setVote('dislike')"
              >
                <Spinner v-if="voteLoading === 'dislike'" class="h-4 w-4" />
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  :class="myVote === 'dislike' ? 'text-foreground' : 'text-muted-foreground'"
                  viewBox="0 0 24 24"
                  :fill="myVote === 'dislike' ? 'currentColor' : 'none'"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M17 14V2" />
                  <path d="M9 18.12 10 14H4.17a2 2 0 0 1-2-2.32l1-7A2 2 0 0 1 5.15 3H17a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.94 1.51L11.17 22A1.65 1.65 0 0 1 8 21.6a10 10 0 0 1 1-3.48Z" />
                </svg>
              </Button>
              <span class="text-sm text-muted-foreground">{{ dislikesCount }}</span>
            </div>
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

          <div
            v-if="canReview && application.status === 'invitation'"
            class="flex flex-wrap gap-3 pt-2"
          >
            <Button
              variant="outline"
              :disabled="!!actionLoading"
              @click="revokeInvitation"
            >
              <Spinner v-if="actionLoading === 'revoke'" class="mr-2 h-4 w-4" />
              Отозвать приглашение
            </Button>
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
