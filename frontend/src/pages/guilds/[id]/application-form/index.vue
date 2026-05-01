<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  Input,
  Label,
  MultiSelect,
  Select,
  Spinner,
} from '@/shared/ui';
import type { SelectOption } from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { guildsApi, type GuildApplicationFormData } from '@/shared/api/guildsApi';
import { charactersApi } from '@/shared/api/charactersApi';
import type { Character } from '@/shared/api/charactersApi';
import { useAuthStore } from '@/stores/auth';
import { applyPageSeo, getSiteOrigin } from '@/shared/lib/usePageSeo';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { isAuthenticated } = storeToRefs(auth);
const guildId = computed(() => Number(route.params.id));

const siteOrigin = getSiteOrigin();
let cleanupSeo: (() => void) | null = null;

const formData = ref<GuildApplicationFormData | null>(null);
const characters = ref<Character[]>([]);
const loading = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);
const success = ref(false);

const selectedCharacterId = ref<string>('');
const fieldValues = ref<Record<number, string>>({});

const isGuest = computed(() => !isAuthenticated.value);
const isFormDisabled = computed(() => isGuest.value || submitting.value || success.value);

const characterOptions = computed<SelectOption[]>(() =>
  characters.value.map((c) => ({ value: String(c.id), label: c.name }))
);

function isImageUrl(val: unknown): boolean {
  if (val == null) return false;
  const s = String(val).trim();
  if (!/^https?:\/\//i.test(s)) return false;
  // базовая проверка расширения; при необходимости можно расширить
  return /\.(jpe?g|png|gif|webp|bmp)(\?.*)?$/i.test(s);
}

const canSubmit = computed(() => {
  if (!isAuthenticated.value) return false;
  if (!formData.value || !selectedCharacterId.value) return false;
  for (const field of formData.value.application_form_fields) {
    const raw = (fieldValues.value[field.id] ?? '').trim();

    if (field.required && !raw) return false;

    if (field.type === 'multiselect' && field.required) {
      try {
        const arr = raw ? (JSON.parse(raw) as unknown) : [];
        if (!Array.isArray(arr) || arr.length === 0) return false;
      } catch {
        return false;
      }
    }

    if (field.type === 'screenshot' && raw && !isImageUrl(raw)) return false;
  }
  return true;
});

const logoUrl = computed(() => {
  const d = formData.value;
  const url = d?.logo_card_url ?? d?.logo_url;
  return url ? storageImageUrl(url) : null;
});

async function loadForm() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  loading.value = true;
  error.value = null;
  try {
    formData.value = await guildsApi.getGuildApplicationForm(guildId.value);
    fieldValues.value = {};
    for (const f of formData.value.application_form_fields) {
      fieldValues.value[f.id] = '';
    }
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) error.value = 'Гильдия не найдена.';
    else error.value = err.message ?? 'Не удалось загрузить форму заявки.';
    formData.value = null;
  } finally {
    loading.value = false;
  }
}

async function loadCharacters() {
  if (!formData.value?.game?.id) return;
  try {
    const list = await charactersApi.getCharacters(formData.value.game.id);
    const guildServerId = formData.value.server?.id;
    characters.value = list.filter((c) => {
      if (c.guild) return false;
      if (guildServerId != null && c.server_id !== guildServerId) return false;
      return true;
    });
    if (characters.value.length > 0 && !selectedCharacterId.value) {
      selectedCharacterId.value = String(characters.value[0].id);
    }
  } catch {
    characters.value = [];
  }
}

watch(
  () => [formData.value?.game?.id, isAuthenticated.value] as const,
  ([gameId, authed]) => {
    if (!authed) {
      characters.value = [];
      selectedCharacterId.value = '';
      return;
    }
    if (gameId) void loadCharacters();
  }
);

watch(
  () => [formData.value?.name, formData.value?.game?.name, formData.value?.server?.name, formData.value?.logo_url, formData.value?.logo_card_url] as const,
  ([guildNameRaw, gameNameRaw, serverNameRaw]) => {
    if (typeof window === 'undefined') return;

    cleanupSeo?.();
    cleanupSeo = null;

    const guildName = (guildNameRaw ?? '').trim();
    if (!guildName) return;

    const gameName = (gameNameRaw ?? '').trim();
    const serverName = (serverNameRaw ?? '').trim();

    const titleGame = gameName ? ` — ${gameName}` : '';
    const title = `Заявка в гильдию ${guildName}${titleGame} — gg-hub`;

    const descriptionParts = [
      `Подать заявку в гильдию «${guildName}» на gg-hub.`,
      gameName ? `Игра: ${gameName}.` : '',
      serverName ? `Сервер: ${serverName}.` : '',
      'Заполните анкету и отправьте заявку руководству гильдии.',
    ].filter(Boolean);
    const description = descriptionParts.join(' ');

    const keywordsParts = [
      `заявка в гильдию ${guildName}`,
      gameName ? `заявка в гильдию ${gameName}` : '',
      serverName ? `гильдия ${serverName}` : '',
      'анкета в гильдию',
      'вступить в гильдию',
      'каталог гильдий',
      'gg-hub',
    ]
      .map((s) => s?.trim())
      .filter((s): s is string => !!s);
    const keywords = [...new Set(keywordsParts)].join(', ');

    const canonicalUrl = `${siteOrigin}/guilds/${guildId.value}/application-form`;
    const ogImageUrlRaw = formData.value?.logo_card_url ?? formData.value?.logo_url ?? null;
    const ogImageUrl = ogImageUrlRaw ? storageImageUrl(ogImageUrlRaw) : undefined;

    cleanupSeo = applyPageSeo({
      title,
      description,
      keywords,
      canonicalUrl,
      ogType: 'website',
      ogImageUrl,
    });
  },
  { immediate: true },
);

onMounted(async () => {
  await loadForm();
});

async function submit() {
  if (!isAuthenticated.value) {
    error.value = 'Чтобы подать заявку, зарегистрируйтесь или войдите в аккаунт.';
    return;
  }
  if (!formData.value || !selectedCharacterId.value || !canSubmit.value || submitting.value) return;
  submitting.value = true;
  error.value = null;
  try {
    // Дополнительная защита на случай обхода disabled-кнопки
    for (const field of formData.value.application_form_fields) {
      const raw = (fieldValues.value[field.id] ?? '').trim();
      if (field.type === 'screenshot' && raw && !isImageUrl(raw)) {
        error.value = 'Проверьте ссылки на скриншоты — укажите корректные URL изображений (.jpg, .png и т.д.).';
        submitting.value = false;
        return;
      }
    }

    const formDataPayload: Record<number, string> = {};
    for (const [id, val] of Object.entries(fieldValues.value)) {
      formDataPayload[Number(id)] = String(val ?? '');
    }
    await guildsApi.submitGuildApplication(formData.value.id, {
      character_id: Number(selectedCharacterId.value),
      form_data: formDataPayload,
    });
    success.value = true;
  } catch (e: unknown) {
    const err = e as Error & { data?: { message?: string; errors?: Record<string, string[]> } };
    const msg = err.data?.message ?? err.data?.errors?.character_id?.[0] ?? err.data?.errors?.guild?.[0] ?? err.message;
    error.value = msg ?? 'Не удалось отправить заявку.';
  } finally {
    submitting.value = false;
  }
}

function setFieldValue(fieldId: number, value: string) {
  fieldValues.value = { ...fieldValues.value, [fieldId]: value };
}

function multiselectFieldValue(fieldId: number): string[] {
  const raw = fieldValues.value[fieldId] ?? '';
  if (!raw.trim()) return [];
  try {
    const parsed = JSON.parse(raw) as unknown;
    return Array.isArray(parsed) ? parsed.filter((x): x is string => typeof x === 'string') : [];
  } catch {
    return [];
  }
}

function setMultiselectFieldValue(fieldId: number, value: (string | number)[]) {
  const arr = value.map((v) => String(v));
  setFieldValue(fieldId, JSON.stringify(arr));
}

function onTextareaInput(fieldId: number, e: Event) {
  setFieldValue(fieldId, (e.target as HTMLTextAreaElement).value);
}
</script>

<template>
  <div class="container py-6">
    <Card v-if="loading" class="max-w-2xl mx-auto">
      <CardContent class="flex items-center justify-center py-12">
        <Spinner class="h-8 w-8" />
      </CardContent>
    </Card>

    <template v-else-if="formData">
      <Card class="max-w-2xl mx-auto">
        <CardHeader class="flex flex-row items-center gap-4 gap-y-2 flex-wrap">
          <div
            v-if="logoUrl"
            class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-muted"
          >
            <img :src="logoUrl" :alt="formData.name" class="h-full w-full object-cover" />
          </div>
          <div class="min-w-0 flex-1">
            <CardTitle class="text-xl">{{ formData.name }}</CardTitle>
            <p v-if="formData.game" class="mt-0.5 text-sm text-muted-foreground">
              {{ formData.game.name }}
              <template v-if="formData.server"> · {{ formData.server.name }}</template>
            </p>
          </div>
        </CardHeader>
        <CardContent class="space-y-6">
          <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
          <p v-if="success" class="text-sm text-green-600 dark:text-green-400">
            Заявка успешно отправлена. Ожидайте решения руководства гильдии.
          </p>

          <template v-if="!formData.is_recruiting">
            <p class="text-muted-foreground">
              В данную гильдию сейчас закрыт набор. Попробуйте позже или выберите другую гильдию.
            </p>
            <Button variant="outline" @click="router.push({ name: 'guilds' })">
              К списку гильдий
            </Button>
          </template>
          <template v-else-if="success">
            <Button variant="outline" @click="router.push({ name: 'guild-show', params: { id: String(guildId) } })">
              К гильдии
            </Button>
          </template>

          <form
            v-else
            class="space-y-6"
            @submit.prevent="submit"
          >
            <div
              v-if="isGuest"
              class="rounded-lg border bg-muted/30 px-4 py-3 text-sm"
            >
              <p class="text-muted-foreground">
                Поля формы доступны для просмотра. Чтобы подать заявку в гильдию — зарегистрируйтесь на сайте или войдите в аккаунт.
              </p>
              <div class="mt-3 flex flex-wrap gap-3">
                <Button type="button" @click="router.push({ name: 'register', query: { redirect: route.fullPath } })">
                  Регистрация
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  @click="router.push({ name: 'login', query: { redirect: route.fullPath } })"
                >
                  Войти
                </Button>
              </div>
            </div>

            <div v-if="(formData.application_form_description ?? '').trim()" class="space-y-2">
              <p class="text-sm font-medium text-foreground">Описание</p>
              <p class="whitespace-pre-wrap text-sm text-muted-foreground">{{ formData.application_form_description }}</p>
            </div>

            <div class="space-y-2">
              <Label for="character">Персонаж *</Label>
              <Select
                id="character"
                v-model="selectedCharacterId"
                :options="characterOptions"
                placeholder="Выберите персонажа"
                :disabled="isFormDisabled"
                required
              />
              <p v-if="isGuest" class="text-xs text-muted-foreground">
                Выбор персонажа доступен после входа в аккаунт.
              </p>
              <p v-else-if="characterOptions.length === 0" class="text-xs text-muted-foreground">
                Нет подходящих персонажей (нужен персонаж той же игры и сервера, не состоящий в гильдии).
                <router-link
                  :to="{ name: 'my-characters-create' }"
                  class="text-primary underline"
                >
                  Создать персонажа
                </router-link>
              </p>
            </div>

            <div
              v-for="field in formData.application_form_fields"
              :key="field.id"
              class="space-y-2"
            >
              <Label :for="`field-${field.id}`">
                {{ field.name }}{{ field.required ? ' *' : '' }}
              </Label>
              <template v-if="field.type === 'select' && field.options?.length">
                <Select
                  :id="`field-${field.id}`"
                  :model-value="fieldValues[field.id] ?? ''"
                  :options="field.options.map((o) => ({ value: o, label: o }))"
                  :placeholder="field.required ? 'Выберите вариант' : 'Не выбрано'"
                  :disabled="isFormDisabled"
                  :required="field.required"
                  trigger-class="w-full"
                  @update:model-value="setFieldValue(field.id, $event)"
                />
              </template>
              <template v-else-if="field.type === 'multiselect' && field.options?.length">
                <MultiSelect
                  :id="`field-${field.id}`"
                  :model-value="multiselectFieldValue(field.id)"
                  :options="field.options.map((o) => ({ value: o, label: o }))"
                  placeholder="Выберите варианты"
                  :disabled="isFormDisabled"
                  search-placeholder="Поиск..."
                  empty-text="Нет вариантов"
                  trigger-class="w-full"
                  @update:model-value="setMultiselectFieldValue(field.id, $event)"
                />
              </template>
              <textarea
                v-else-if="field.type === 'textarea'"
                :id="`field-${field.id}`"
                :value="fieldValues[field.id] ?? ''"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                :placeholder="field.required ? 'Обязательное поле' : ''"
                :disabled="isFormDisabled"
                :required="field.required"
                @input="onTextareaInput(field.id, $event)"
              />
              <Input
                v-else
                :id="`field-${field.id}`"
                type="text"
                :model-value="fieldValues[field.id] ?? ''"
                :placeholder="field.type === 'screenshot' ? 'Ссылка на скриншот (.jpg, .png и т.д.)' : (field.required ? 'Обязательное поле' : '')"
                :disabled="isFormDisabled"
                :required="field.required"
                @update:model-value="setFieldValue(field.id, $event)"
              />
              <p
                v-if="field.type === 'screenshot' && (fieldValues[field.id] ?? '').trim() && !isImageUrl(fieldValues[field.id])"
                class="text-xs text-destructive"
              >
                Укажите корректную ссылку на изображение (http(s)://..., .jpg, .jpeg, .png, .gif, .webp, .bmp).
              </p>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
              <Button
                type="submit"
                :disabled="!canSubmit || submitting || isGuest"
              >
                <Spinner v-if="submitting" class="mr-2 h-4 w-4" />
                {{ submitting ? 'Отправка…' : 'Подать заявку' }}
              </Button>
              <Button
                type="button"
                variant="outline"
                @click="router.push({ name: 'guild-show', params: { id: String(guildId) } })"
              >
                Отмена
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </template>

    <Card v-else class="max-w-2xl mx-auto">
      <CardContent class="py-8 text-center">
        <p class="text-muted-foreground">{{ error ?? 'Гильдия не найдена.' }}</p>
        <Button variant="outline" class="mt-4" @click="router.push({ name: 'guilds' })">
          К списку гильдий
        </Button>
      </CardContent>
    </Card>
  </div>
</template>
