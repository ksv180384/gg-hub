<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label, Separator, SelectRoot, SelectTrigger, SelectValue, SelectContent, SelectItem, RichTextEditor } from '@/shared/ui';
import { useSiteContextStore } from '@/stores/siteContext';
import { charactersApi, type Character } from '@/shared/api/charactersApi';
import { guildsApi, type UserGuildItem } from '@/shared/api/guildsApi';
import { postsApi, type CreatePostPayload, type Post } from '@/shared/api/postsApi';

interface Props {
  postId?: number | null;
}

const props = defineProps<Props>();

const route = useRoute();
const router = useRouter();
const siteContext = useSiteContextStore();

const game = computed(() => siteContext.game);
const effectivePostId = computed<number | null>(() => {
  if (props.postId != null) return props.postId;
  const fromRoute = Number(route.params.id);
  return Number.isFinite(fromRoute) ? fromRoute : null;
});

const isEdit = computed(() => effectivePostId.value != null);

const title = ref<string>('');
const body = ref<string>('');
const characterId = ref<number | null>(null);
const guildId = ref<number | null>(null);

const characters = ref<Character[]>([]);
const userGuilds = ref<UserGuildItem[]>([]);

const guildPermissionSlugs = ref<Record<number, string[]>>({});

const isVisibleGlobal = ref<boolean>(false);
const isVisibleGuild = ref<boolean>(false);

const globalVisibilityType = ref<'anonymous' | 'guild' | null>(null);
const statusGlobal = ref<'published' | 'draft' | 'hidden'>('draft');
const statusGuild = ref<'published' | 'draft' | 'hidden'>('draft');

const statusOptions = [
  { value: 'published' as const, label: 'Опубликован' },
  { value: 'draft' as const, label: 'Черновик' },
  { value: 'hidden' as const, label: 'Скрыт' },
] as const;

const bodyPreviewMode = ref(false);
const loading = ref(false);
const loadError = ref<string | null>(null);
const submitError = ref<string | null>(null);
/** Пост полностью заблокирован (общие и гильдия) — редактирование недоступно */
const isBlocked = ref(false);
/** Пост заблокирован для общего просмотра — статус «Общие» изменить нельзя */
const isGlobalBlocked = ref(false);
/** Пост заблокирован для гильдии модератором — статус для гильдии изменить нельзя */
const isGuildBlocked = ref(false);
/** Статус «Общие» на модерации — изменение заблокировано */
const isPendingGlobal = ref(false);
/** Статус «Гильдия» на модерации — изменение заблокировано */
const isPendingGuild = ref(false);

const selectedCharacter = computed(() =>
  characterId.value != null ? characters.value.find((c) => c.id === characterId.value) ?? null : null,
);

const availableGuilds = computed<UserGuildItem[]>(() => {
  const ch = selectedCharacter.value;
  if (!ch?.guild) return [];
  return userGuilds.value.filter((g) => g.id === ch.guild!.id);
});

const canChooseGuild = computed(() => availableGuilds.value.length > 1);

const profileVisibilityText = computed(() => '');

async function loadInitialData() {
  loadError.value = null;
  try {
    const g = game.value;
    if (g?.id) {
      characters.value = await charactersApi.getCharacters(g.id);
      userGuilds.value = await guildsApi.getMyGuildsForGame(g.id);
    } else {
      characters.value = [];
      userGuilds.value = [];
    }

    if (isEdit.value && effectivePostId.value) {
      const post: Post = await postsApi.getPost(effectivePostId.value);
      isBlocked.value = post.status_global === 'blocked' && post.status_guild === 'blocked';
      isGlobalBlocked.value = post.status_global === 'blocked';
      isGuildBlocked.value = post.status_guild === 'blocked';
      isPendingGlobal.value = post.status_global === 'pending';
      isPendingGuild.value = post.status_guild === 'pending';
      title.value = post.title ?? '';
      body.value = post.body?.startsWith('<') ? post.body : `<p>${(post.body || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}</p>`;
      characterId.value = post.character_id;
      guildId.value = post.guild_id;
      isVisibleGlobal.value = post.is_visible_global;
      isVisibleGuild.value = post.is_visible_guild;

      if (post.is_visible_global) {
        if (post.is_global_as_guild) {
          globalVisibilityType.value = 'guild';
        } else if (post.is_anonymous) {
          globalVisibilityType.value = 'anonymous';
        } else {
          globalVisibilityType.value = null;
        }
      } else {
        globalVisibilityType.value = null;
      }

      statusGlobal.value = (post.status_global === 'published' || post.status_global === 'draft' || post.status_global === 'hidden')
        ? post.status_global
        : 'hidden';
      statusGuild.value = (post.status_guild === 'published' || post.status_guild === 'draft' || post.status_guild === 'hidden')
        ? post.status_guild
        : 'hidden';
    } else {
      isBlocked.value = false;
      isGlobalBlocked.value = false;
      isGuildBlocked.value = false;
      isPendingGlobal.value = false;
      isPendingGuild.value = false;
    }
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : 'Не удалось загрузить данные для формы';
  }
}

const globalHint = computed(() => {
  if (!isVisibleGlobal.value) return '';
  if (globalVisibilityType.value === 'anonymous') return 'Пост будет опубликован в разделе «Общие» анонимно.';
  if (globalVisibilityType.value === 'guild') return 'Пост будет опубликован в разделе «Общие» от имени выбранной гильдии (если у вас есть соответствующее право в гильдии).';
  return 'Пост будет опубликован в разделе «Общие» после модерации.';
});

const guildHint = computed(() => {
  if (!isVisibleGuild.value) return '';
  if (!selectedCharacter.value) return 'Выберите персонажа, от имени которого пишете пост.';
  if (!selectedCharacter.value.guild) return 'Выбранный персонаж не состоит ни в одной гильдии.';
  if (!guildId.value && availableGuilds.value.length > 1) return 'Выберите гильдию, в которой будет отображаться пост.';
  const slugs = guildId.value != null ? guildPermissionSlugs.value[guildId.value] : undefined;
  if (guildId.value && (!slugs || !slugs.includes('dobavliat-post'))) {
    return 'У вас нет прав публиковать посты в выбранной гильдии.';
  }
  if (globalVisibilityType.value === 'anonymous') return 'Пост будет опубликован в разделе гильдии анонимно.';
  return 'Пост будет опубликован в разделе гильдии после модерации.';
});

const canPublishToGuild = computed(() => {
  if (!guildId.value) return false;
  const slugs = guildPermissionSlugs.value[guildId.value];
  if (!slugs) return false;
  return slugs.includes('dobavliat-post');
});

const canPublishGlobalAsGuild = computed(() => {
  if (!guildId.value) return false;
  const slugs = guildPermissionSlugs.value[guildId.value];
  if (!slugs) return false;
  return slugs.includes('sozdavat-posty-ot-imeni-gildii');
});

function isBodyEmpty(html: string): boolean {
  const trimmed = html.trim();
  if (!trimmed) return true;
  // Есть текст (без тегов)
  const stripped = trimmed.replace(/<[^>]*>/g, '').trim();
  if (stripped) return false;
  // Только теги — считаем непустым, если есть видео (YouTube, VK)
  const hasVideo =
    trimmed.includes('data-video-embed') ||
    trimmed.includes('<iframe') ||
    trimmed.includes('youtube.com/embed') ||
    trimmed.includes('vk.com/video_ext');
  return !hasVideo;
}

async function submit() {
  if (isBlocked.value) return;
  if (isBodyEmpty(body.value)) {
    submitError.value = 'Введите текст поста.';
    return;
  }

  loading.value = true;
  submitError.value = null;
  try {
    const payload: CreatePostPayload = {
      title: title.value.trim() === '' ? null : title.value.trim(),
      body: body.value,
      character_id: characterId.value,
      guild_id: guildId.value,
      game_id: game.value?.id ?? null,
      is_visible_global: isVisibleGlobal.value,
      is_visible_guild: isGuildBlocked.value ? true : isVisibleGuild.value,
      global_visibility_type: isVisibleGlobal.value ? globalVisibilityType.value : null,
      status_global: isPendingGlobal ? 'hidden' : (isVisibleGlobal.value ? statusGlobal.value : 'hidden'),
      status_guild: isPendingGuild ? 'hidden' : (isVisibleGuild.value ? statusGuild.value : 'hidden'),
    };
    if (isPendingGlobal) (payload as Record<string, unknown>).status_global = 'pending';
    if (isPendingGuild) (payload as Record<string, unknown>).status_guild = 'pending';

    if (isEdit.value && effectivePostId.value) {
      await postsApi.updatePost(effectivePostId.value, payload);
    } else {
      await postsApi.createPost(payload);
    }

    await router.push({ name: 'my-posts' });
  } catch (e) {
    submitError.value = e instanceof Error ? e.message : 'Не удалось сохранить пост';
  } finally {
    loading.value = false;
  }
}

watch(
  () => isVisibleGuild.value,
  (newVal) => {
    if (!newVal) {
      return;
    }
    if (guildId.value) {
      return;
    }
    const ch = selectedCharacter.value;
    if (ch?.guild) {
      guildId.value = ch.guild.id;
    } else {
      guildId.value = null;
    }
  }
);

watch(
  () => characterId.value,
  () => {
    const ch = selectedCharacter.value;
    if (ch?.guild) {
      guildId.value = ch.guild.id;
    } else {
      guildId.value = null;
      isVisibleGuild.value = false;
    }
  }
);

watch(
  () => isVisibleGuild.value,
  (newVal) => {
    if (!newVal && globalVisibilityType.value === 'guild') {
      globalVisibilityType.value = null;
    }
  }
);

watch(isVisibleGlobal, (val) => {
  if (!val) statusGlobal.value = 'hidden';
});

watch(isVisibleGuild, (val) => {
  if (!val) statusGuild.value = 'hidden';
});

watch(
  () => guildId.value,
  async (newId) => {
    if (!newId) return;

    try {
      const guild = await guildsApi.getGuildForSettings(newId);
      guildPermissionSlugs.value[newId] = guild.my_permission_slugs ?? [];
    } catch {
      guildPermissionSlugs.value[newId] = [];
    }

    if (guildPermissionSlugs.value[newId] && !canPublishToGuild.value) {
      isVisibleGuild.value = false;
    }
  }
);

onMounted(() => {
  loadInitialData();
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-3xl space-y-6">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h1 class="mb-1 text-3xl font-bold tracking-tight">
            {{ isEdit ? 'Редактирование поста' : 'Новый пост' }}
          </h1>
          <p class="text-muted-foreground">
            {{ isEdit ? 'Измените содержимое и видимость поста.' : 'Укажите текст и выберите, кому будет виден пост.' }}
          </p>
        </div>
        <Button variant="link" size="sm" class="min-h-9 cursor-pointer" @click="router.back()">
          Назад
        </Button>
      </div>

      <Card v-if="isBlocked" class="border-destructive/50 bg-destructive/5">
        <CardContent class="pt-6">
          <p class="text-sm font-medium text-destructive">
            Пост заблокирован. Редактирование недоступно.
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            Вы можете только просматривать содержимое. Для разблокировки обратитесь к администратору.
          </p>
        </CardContent>
      </Card>

      <template v-if="!isBlocked">
      <Card>
        <CardHeader>
          <CardTitle>Содержимое поста</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <Label for="title">Заголовок (необязательно)</Label>
            <Input id="title" v-model="title" placeholder="Например, «Советы по рейдам в пятницу»" />
          </div>
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2 border-b border-border pb-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                :class="{ 'bg-muted': !bodyPreviewMode }"
                @click="bodyPreviewMode = false"
              >
                Редактирование
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                :class="{ 'bg-muted': bodyPreviewMode }"
                @click="bodyPreviewMode = true"
              >
                Предпросмотр
              </Button>
            </div>
            <Label for="body">Текст поста *</Label>
            <div v-show="!bodyPreviewMode">
              <RichTextEditor
                id="body"
                v-model="body"
                placeholder="Напишите текст вашего поста…"
                :disabled="loading"
              />
            </div>
            <div
              v-show="bodyPreviewMode"
              class="min-h-[200px] rounded-md border border-input bg-muted/30 px-3 py-3 text-sm"
            >
              <div
                v-if="body"
                class="prose prose-sm max-w-none text-muted-foreground dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:decoration-blue-600/40 [&_a]:underline-offset-2 hover:[&_a]:text-blue-700 dark:[&_a]:text-blue-400 dark:hover:[&_a]:text-blue-300 dark:[&_a]:decoration-blue-400/50 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_ol]:space-y-1 [&_li]:my-0.5"
                v-html="body"
              />
              <p v-else class="text-muted-foreground">Нет текста. Переключитесь в режим редактирования и добавьте описание.</p>
            </div>
          </div>
          <div>
            <Label for="character">Персонаж (от кого пишете)</Label>
            <SelectRoot
              :model-value="characterId !== null ? String(characterId) : undefined"
              @update:model-value="(val) => { characterId = val ? Number(val) : null; }"
            >
              <SelectTrigger id="character" class="w-full">
                <SelectValue placeholder="Без привязки к персонажу" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="c in characters"
                  :key="c.id"
                  :value="String(c.id)"
                >
                  {{ c.name }}
                </SelectItem>
              </SelectContent>
            </SelectRoot>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Кому будет виден пост</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div
            class="flex items-start justify-between gap-4"
            :class="{ 'opacity-70': isPendingGlobal || isGlobalBlocked }"
          >
            <div class="space-y-1">
              <Label for="visible-global">Для всех (раздел «Общие»)</Label>
              <p v-if="isGlobalBlocked" class="text-xs text-muted-foreground">
                Пост заблокирован для общего просмотра. Изменить нельзя.
              </p>
              <p v-else-if="isPendingGlobal" class="text-xs text-muted-foreground">
                Пост на модерации. Параметры раздела «Общие» пока изменить нельзя.
              </p>
              <p v-else class="text-xs text-muted-foreground">
                Пост будет виден всем пользователям сайта после общей модерации.
              </p>
            </div>
            <label
              class="inline-flex cursor-pointer items-center gap-2"
              :class="{ 'cursor-not-allowed opacity-60': isPendingGlobal || isGlobalBlocked }"
            >
              <input
                id="visible-global"
                v-model="isVisibleGlobal"
                type="checkbox"
                class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="isPendingGlobal || isGlobalBlocked"
              />
              <span class="text-sm">Включить</span>
            </label>
          </div>

          <Separator />

          <div
            class="flex items-start justify-between gap-4"
            :class="{ 'opacity-70': isGuildBlocked || isPendingGuild }"
          >
            <div class="space-y-1">
              <Label for="visible-guild">Для членов гильдии (раздел «Гильдия»)</Label>
              <p v-if="isGuildBlocked" class="text-xs text-muted-foreground">
                Пост заблокирован для гильдии модератором. Изменить статус для гильдии нельзя.
              </p>
              <p v-else-if="isPendingGuild" class="text-xs text-muted-foreground">
                Пост на модерации гильдии. Параметры раздела «Гильдия» пока изменить нельзя.
              </p>
              <p v-else class="text-xs text-muted-foreground">
                Пост будет виден участникам выбранной гильдии после гильдейской модерации.
              </p>
            </div>
            <label
              class="inline-flex cursor-pointer items-center gap-2"
              :class="{ 'cursor-not-allowed opacity-60': isGuildBlocked || isPendingGuild }"
            >
              <input
                id="visible-guild"
                v-model="isVisibleGuild"
                type="checkbox"
                class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canPublishToGuild || isGuildBlocked || isPendingGuild"
              />
              <span class="text-sm">Включить</span>
            </label>
          </div>

          <Separator />

          <div
            v-if="isVisibleGlobal || isVisibleGuild || isGuildBlocked || isGlobalBlocked || isPendingGlobal || isPendingGuild"
            class="space-y-6"
          >
            <div v-if="!isGlobalBlocked" class="space-y-3">
              <div class="space-y-2">
                <label
                  class="flex cursor-pointer items-center space-x-2 text-sm"
                  :class="{ 'cursor-not-allowed opacity-60': isPendingGlobal }"
                >
                  <input
                    id="global-anon"
                    v-model="globalVisibilityType"
                    type="radio"
                    class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    value="anonymous"
                    :disabled="isPendingGlobal"
                  />
                  <span>Анонимно (имя автора отображаться не будет)</span>
                </label>
                <label
                  v-if="isVisibleGlobal"
                  class="flex cursor-pointer items-center space-x-2 text-sm"
                  :class="{ 'opacity-50 cursor-not-allowed': !canPublishGlobalAsGuild || isPendingGlobal }"
                >
                  <input
                    id="global-guild"
                    v-model="globalVisibilityType"
                    type="radio"
                    class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    value="guild"
                    :disabled="!canPublishGlobalAsGuild || isPendingGlobal"
                  />
                  <span>От имени гильдии</span>
                </label>
              </div>

              <p v-if="globalHint" class="text-xs text-muted-foreground">
                {{ globalHint }}
              </p>
            </div>

            <div v-if="isVisibleGuild && !isGuildBlocked && !isPendingGuild" class="space-y-3">
              <div class="space-y-2">
                <Label for="guild-select">Гильдия</Label>
                <SelectRoot
                  :model-value="guildId !== null ? String(guildId) : undefined"
                  @update:model-value="(val) => { guildId = val ? Number(val) : null; }"
                >
                  <SelectTrigger id="guild-select" class="w-full">
                    <SelectValue
                      :placeholder="
                        !selectedCharacter
                          ? 'Сначала выберите персонажа'
                          : !selectedCharacter.guild
                            ? 'Персонаж не состоит в гильдии'
                            : availableGuilds.length === 1
                              ? availableGuilds[0]?.name
                              : 'Выберите гильдию'
                      "
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="g in availableGuilds"
                      :key="g.id"
                      :value="String(g.id)"
                    >
                      {{ g.name }}
                    </SelectItem>
                  </SelectContent>
                </SelectRoot>
              </div>
              <p v-if="guildHint" class="text-xs text-muted-foreground">
                {{ guildHint }}
              </p>
            </div>
          </div>

          <Separator />

          <div class="grid gap-6 sm:grid-cols-2">
            <div
              class="space-y-3"
              :class="{ 'opacity-60': !isVisibleGlobal || isPendingGlobal || isGlobalBlocked }"
            >
              <Label class="text-sm">Статус для раздела «Общие»</Label>
              <p v-if="isGlobalBlocked" class="text-xs text-muted-foreground">
                Заблокировано для общего просмотра. Изменить нельзя.
              </p>
              <p v-else-if="isPendingGlobal" class="text-xs text-muted-foreground">
                На модерации. Изменить статус нельзя до решения модератора.
              </p>
              <div v-else class="space-y-2 text-sm">
                <label
                  v-for="opt in statusOptions"
                  :key="`global-${opt.value}`"
                  class="flex cursor-pointer items-center space-x-2"
                  :class="{ 'cursor-not-allowed': !isVisibleGlobal }"
                >
                  <input
                    :id="`post-status-global-${opt.value}`"
                    v-model="statusGlobal"
                    type="radio"
                    class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :value="opt.value"
                    :disabled="!isVisibleGlobal"
                  />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
              <p v-if="!isVisibleGlobal && !isPendingGlobal && !isGlobalBlocked" class="text-xs text-muted-foreground">
                Неактивно (галочка «Для всех» выключена). Установлено: Скрыт.
              </p>
            </div>
            <div
              class="space-y-3"
              :class="{ 'opacity-60': !isVisibleGuild || isGuildBlocked || isPendingGuild }"
            >
              <Label class="text-sm">Статус для раздела «Гильдия»</Label>
              <p v-if="isGuildBlocked" class="text-xs text-muted-foreground">
                Заблокировано для гильдии. Изменить нельзя.
              </p>
              <p v-else-if="isPendingGuild" class="text-xs text-muted-foreground">
                На модерации. Изменить статус нельзя до решения модератора гильдии.
              </p>
              <div v-else class="space-y-2 text-sm">
                <label
                  v-for="opt in statusOptions"
                  :key="`guild-${opt.value}`"
                  class="flex cursor-pointer items-center space-x-2"
                  :class="{ 'cursor-not-allowed': !isVisibleGuild }"
                >
                  <input
                    :id="`post-status-guild-${opt.value}`"
                    v-model="statusGuild"
                    type="radio"
                    class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :value="opt.value"
                    :disabled="!isVisibleGuild"
                  />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
              <p v-if="!isVisibleGuild && !isGuildBlocked && !isPendingGuild" class="text-xs text-muted-foreground">
                Неактивно (галочка «Для членов гильдии» выключена). Установлено: Скрыт.
              </p>
            </div>
          </div>


          <p v-if="profileVisibilityText" class="text-sm text-muted-foreground">
            {{ profileVisibilityText }}
          </p>
        </CardContent>
      </Card>

      <div class="flex items-center justify-between gap-4">
        <p v-if="submitError" class="text-sm text-destructive">
          {{ submitError }}
        </p>
        <p v-else-if="loadError" class="text-sm text-destructive">
          {{ loadError }}
        </p>
        <div class="ml-auto flex gap-3">
          <Button variant="outline" @click="router.back()">
            Отмена
          </Button>
          <Button :disabled="loading" @click="submit">
            {{ loading ? 'Сохранение…' : (isEdit ? 'Сохранить пост' : 'Создать пост') }}
          </Button>
        </div>
      </div>
      </template>
    </div>
  </div>
</template>

