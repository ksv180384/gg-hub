<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label, Separator, SelectRoot, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/shared/ui';
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
const status = ref<'published' | 'draft' | 'hidden'>('draft');

const loading = ref(false);
const loadError = ref<string | null>(null);
const submitError = ref<string | null>(null);

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
      title.value = post.title ?? '';
      body.value = post.body;
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

      if ((post.status_global === 'published') || (post.status_guild === 'published')) {
        status.value = 'published';
      } else if ((post.status_global === 'draft') || (post.status_guild === 'draft')) {
        status.value = 'draft';
      } else {
        status.value = 'hidden';
      }
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

async function submit() {
  if (!body.value.trim()) {
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
      is_visible_guild: isVisibleGuild.value,
      global_visibility_type: isVisibleGlobal.value ? globalVisibilityType.value : null,
      status: status.value,
    };

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
        <Button variant="outline" size="sm" class="min-h-9" @click="router.back()">
          Назад
        </Button>
      </div>

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
            <Label for="body">Текст поста</Label>
            <textarea
              id="body"
              v-model="body"
              rows="6"
              class="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              placeholder="Напишите текст вашего поста…"
            />
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
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <Label for="visible-global">Для всех (раздел «Общие»)</Label>
              <p class="text-xs text-muted-foreground">
                Пост будет виден всем пользователям сайта после общей модерации.
              </p>
            </div>
            <label class="inline-flex cursor-pointer items-center gap-2">
              <input
                id="visible-global"
                v-model="isVisibleGlobal"
                type="checkbox"
                class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
              />
              <span class="text-sm">Включить</span>
            </label>
          </div>

          <Separator />

          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <Label for="visible-guild">Для членов гильдии (раздел «Гильдия»)</Label>
              <p class="text-xs text-muted-foreground">
                Пост будет виден участникам выбранной гильдии после гильдейской модерации.
              </p>
            </div>
            <label class="inline-flex cursor-pointer items-center gap-2">
              <input
                id="visible-guild"
                v-model="isVisibleGuild"
                type="checkbox"
                class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canPublishToGuild"
              />
              <span class="text-sm">Включить</span>
            </label>
          </div>

          <Separator />

          <div v-if="isVisibleGlobal || isVisibleGuild" class="space-y-6">
            <div class="space-y-3">
              <div class="space-y-2">
                <label class="flex cursor-pointer items-center space-x-2 text-sm">
                  <input
                    id="global-anon"
                    v-model="globalVisibilityType"
                    type="radio"
                    class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    value="anonymous"
                  />
                  <span>Анонимно (имя автора отображаться не будет)</span>
                </label>
                <label
                  v-if="isVisibleGlobal"
                  class="flex cursor-pointer items-center space-x-2 text-sm"
                  :class="{ 'opacity-50 cursor-not-allowed': !canPublishGlobalAsGuild }"
                >
                  <input
                    id="global-guild"
                    v-model="globalVisibilityType"
                    type="radio"
                    class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    value="guild"
                    :disabled="!canPublishGlobalAsGuild"
                  />
                  <span>От имени гильдии</span>
                </label>
              </div>

              <p v-if="globalHint" class="text-xs text-muted-foreground">
                {{ globalHint }}
              </p>
            </div>

            <div v-if="isVisibleGuild" class="space-y-3">
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

          <div class="space-y-3">
            <Label class="text-sm">Статус</Label>
            <div class="space-y-2 text-sm">
              <label class="flex cursor-pointer items-center space-x-2">
                <input
                  id="post-status-published"
                  v-model="status"
                  type="radio"
                  class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  value="published"
                />
                <span>Опубликован</span>
              </label>
              <label class="flex cursor-pointer items-center space-x-2">
                <input
                  id="post-status-draft"
                  v-model="status"
                  type="radio"
                  class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  value="draft"
                />
                <span>Черновик</span>
              </label>
              <label class="flex cursor-pointer items-center space-x-2">
                <input
                  id="post-status-hidden"
                  v-model="status"
                  type="radio"
                  class="h-4 w-4 border-input text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  value="hidden"
                />
                <span>Скрыт</span>
              </label>
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
    </div>
  </div>
</template>

