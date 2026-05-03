<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue';
import { computed, nextTick, ref, watch } from 'vue';
import {
  ComboboxAnchor,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxPortal,
  ComboboxRoot,
  ComboboxTrigger,
  ComboboxViewport,
} from 'radix-vue';
import { Button, Input, Label } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { tagsApi, type Tag } from '@/shared/api/tagsApi';
import { sortTagsForPicker } from '@/shared/lib/tagPickerOrder';
import { toastError } from '@/shared/lib/toast';

const TAG_CREATE_VALUE_PREFIX = '__create__:';

const allTags = defineModel<Tag[]>('allTags', { required: true });
const selectedTagIds = defineModel<number[]>('selectedTagIds', { required: true });

const props = withDefaults(
  defineProps<{
    /** Уникальный id для поля (Label for= и фокус). */
    inputId: string;
    disabled?: boolean;
    label?: string;
    placeholder?: string;
    /** Показать кнопку удаления у пункта (например, только свои теги). */
    canDeleteTag?: (tag: Tag) => boolean;
    /** Разрешить создание нового тега из строки поиска и блок «Добавить новый». */
    allowCreateTag?: boolean;
    /** Если задан — создание через POST /guilds/:id/tags (тег гильдии). Иначе POST /tags (тег пользователя). */
    tagCreateGuildId?: number | null;
  }>(),
  {
    disabled: false,
    label: 'Добавить тег',
    placeholder: 'Выберите или введите тег',
    allowCreateTag: true,
    tagCreateGuildId: null,
  }
);

const emit = defineEmits<{
  /** Запрос на удаление тега — родитель открывает диалог и вызывает API. */
  deleteTag: [tag: Tag];
}>();

const tagComboModel = ref('');
const tagPickerOpen = ref(false);
const tagSearchTerm = ref('');
const creatingTag = ref(false);
const newTagName = ref('');
const addingNewTag = ref(false);
const newTagInputRef = ref<{ focus: (options?: FocusOptions) => void } | null>(null);
const tagSearchInputRef = ref<ComponentPublicInstance | null>(null);

const tagsNotSelected = computed(() =>
  sortTagsForPicker(allTags.value.filter((t) => !selectedTagIds.value.includes(t.id)))
);

const tagSearchTrimmed = computed(() => tagSearchTerm.value.trim());

const showTagCreateOption = computed(() => {
  if (props.disabled || !props.allowCreateTag) return false;
  const n = tagSearchTrimmed.value;
  if (n.length === 0 || n.length > 20 || creatingTag.value) return false;
  const lower = n.toLowerCase();
  if (tagsNotSelected.value.some((t) => t.name.trim().toLowerCase() === lower)) return false;
  const selectedTags = allTags.value.filter((t) => selectedTagIds.value.includes(t.id));
  if (selectedTags.some((t) => t.name.trim().toLowerCase() === lower)) return false;
  return true;
});

const tagCreateOptionValue = computed(() =>
  showTagCreateOption.value
    ? `${TAG_CREATE_VALUE_PREFIX}${encodeURIComponent(tagSearchTrimmed.value)}`
    : ''
);

function tagNameMatchesSearch(tag: Tag, term: string): boolean {
  const q = term.trim().toLowerCase();
  if (!q) return true;
  return tag.name.trim().toLowerCase().includes(q);
}

/**
 * Только теги для списка: при поиске не рендерим лишние строки — иначе radix скрывает только
 * ComboboxItem, а обёртка и кнопка «удалить» остаются (пустые полосы).
 */
const tagsNotSelectedFiltered = computed(() =>
  tagsNotSelected.value.filter((t) => tagNameMatchesSearch(t, tagSearchTerm.value))
);

/** По умолчанию radix-vue фильтрует только по `value` (у нас — id тега), а не по названию. */
function tagComboFilter(values: string[], term: string): string[] {
  const q = term.trim().toLowerCase();
  if (!q) return values;
  return values.filter((v) => {
    if (v.startsWith(TAG_CREATE_VALUE_PREFIX)) return true;
    const id = Number(v);
    if (!Number.isFinite(id)) return false;
    const tag = allTags.value.find((t) => t.id === id);
    return tag ? tagNameMatchesSearch(tag, term) : false;
  });
}

function showDelete(tag: Tag): boolean {
  if (props.disabled) return false;
  return props.canDeleteTag?.(tag) ?? false;
}

function onTagAnchorPointerDown() {
  if (props.disabled) return;
  if (!tagPickerOpen.value) {
    tagPickerOpen.value = true;
  }
}

function focusComboboxInput() {
  const root = tagSearchInputRef.value?.$el as HTMLElement | undefined;
  const fromRef =
    root instanceof HTMLInputElement
      ? root
      : root?.querySelector?.('input');
  if (fromRef instanceof HTMLInputElement) {
    fromRef.focus({ preventScroll: true });
    return;
  }
  const node = document.getElementById(props.inputId);
  const input =
    node instanceof HTMLInputElement ? node : node?.querySelector?.('input');
  if (input instanceof HTMLInputElement) {
    input.focus({ preventScroll: true });
  }
}

/** После открытия портала/фокуса radix — стабильно возвращаем курсор в поле поиска. */
function queueFocusComboboxInput() {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      focusComboboxInput();
    });
  });
}

/** Кнопка-стрелка иначе забирает фокус с поля ввода. */
function onTagChevronMouseDown(e: MouseEvent) {
  if (props.disabled) return;
  e.preventDefault();
}

watch(tagComboModel, async (v) => {
  if (!v) return;
  if (v.startsWith(TAG_CREATE_VALUE_PREFIX)) {
    const raw = decodeURIComponent(v.slice(TAG_CREATE_VALUE_PREFIX.length));
    tagComboModel.value = '';
    await createAndAddTagFromQuery(raw);
    return;
  }
  const id = Number(v);
  if (!id || selectedTagIds.value.includes(id)) {
    nextTick(() => {
      tagComboModel.value = '';
    });
    return;
  }
  selectedTagIds.value = [...selectedTagIds.value, id];
  tagPickerOpen.value = false;
  nextTick(() => {
    tagComboModel.value = '';
  });
});

watch(tagPickerOpen, async (open) => {
  if (!open) {
    tagSearchTerm.value = '';
    addingNewTag.value = false;
    newTagName.value = '';
    return;
  }
  if (props.disabled) return;
  await nextTick();
  queueFocusComboboxInput();
});

async function createAndAddTagFromQuery(trimName: string): Promise<boolean> {
  const name = trimName.trim();
  if (!name || creatingTag.value || props.disabled) return false;
  creatingTag.value = true;
  try {
    const tag =
      props.tagCreateGuildId != null
        ? await tagsApi.createGuildTag(props.tagCreateGuildId, { name })
        : await tagsApi.createTag({ name });
    if (!allTags.value.some((t) => t.id === tag.id)) {
      allTags.value = [...allTags.value, tag];
    }
    if (!selectedTagIds.value.includes(tag.id)) {
      selectedTagIds.value = [...selectedTagIds.value, tag.id];
    }
    tagPickerOpen.value = false;
    return true;
  } catch (e) {
    const msg = e instanceof Error ? e.message : 'Не удалось создать тег';
    toastError(msg);
    return false;
  } finally {
    creatingTag.value = false;
  }
}

async function startAddingNewTag() {
  if (props.disabled || !props.allowCreateTag) return;
  addingNewTag.value = true;
  await nextTick();
  await nextTick();
  newTagInputRef.value?.focus();
}

function cancelNewTag() {
  addingNewTag.value = false;
  newTagName.value = '';
}

async function createAndAddTag() {
  const trimName = newTagName.value.trim();
  if (!trimName || creatingTag.value || props.disabled) return;
  const ok = await createAndAddTagFromQuery(trimName);
  if (ok) {
    newTagName.value = '';
    addingNewTag.value = false;
  }
}

function onDeleteClick(tag: Tag) {
  emit('deleteTag', tag);
}
</script>

<template>
  <div class="space-y-1">
    <Label :for="inputId" class="text-muted-foreground">{{ label }}</Label>
    <ClientOnly>
      <ComboboxRoot
        v-model="tagComboModel"
        v-model:open="tagPickerOpen"
        v-model:search-term="tagSearchTerm"
        class="relative w-full"
        :disabled="disabled"
        :filter-function="tagComboFilter"
        :reset-search-term-on-select="true"
        :reset-search-term-on-blur="true"
      >
        <ComboboxAnchor
          class="flex h-9 w-full min-w-0 cursor-text items-center gap-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background focus-within:ring-1 focus-within:ring-ring data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
          @pointerdown="onTagAnchorPointerDown"
        >
          <ComboboxInput
            ref="tagSearchInputRef"
            :id="inputId"
            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm shadow-none placeholder:text-muted-foreground outline-none focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            :placeholder="placeholder"
            autocomplete="off"
            maxlength="20"
          />
          <ComboboxTrigger
            class="inline-flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-sm text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none"
            aria-label="Открыть список тегов"
            @mousedown="onTagChevronMouseDown"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="m6 9 6 6 6-6" />
            </svg>
          </ComboboxTrigger>
        </ComboboxAnchor>
        <ComboboxPortal>
          <ComboboxContent
            position="popper"
            side="bottom"
            align="start"
            :side-offset="4"
            class="z-50 flex max-h-[min(24rem,var(--radix-combobox-content-available-height))] w-[var(--radix-combobox-anchor-width)] flex-col overflow-hidden rounded-md border bg-popover p-0 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2"
            @open-auto-focus="(e: Event) => e.preventDefault()"
          >
            <ComboboxViewport
              class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1"
            >
              <ComboboxEmpty class="px-2 py-3 text-center text-sm text-muted-foreground">
                Ничего не найдено
              </ComboboxEmpty>
              <ComboboxItem
                v-if="showTagCreateOption"
                :value="tagCreateOptionValue"
                :text-value="tagSearchTrimmed"
                class="relative cursor-default select-none rounded-sm px-2 py-1.5 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
              >
                <span class="text-muted-foreground">Создать</span>
                «{{ tagSearchTrimmed }}»
              </ComboboxItem>
              <div
                v-for="tag in tagsNotSelectedFiltered"
                :key="tag.id"
                class="flex w-full min-w-0 items-stretch rounded-sm transition-colors hover:bg-accent hover:text-accent-foreground has-[[data-highlighted]]:bg-accent has-[[data-highlighted]]:text-accent-foreground"
              >
                <ComboboxItem
                  :value="String(tag.id)"
                  :text-value="tag.name"
                  class="relative min-w-0 flex-1 cursor-default select-none rounded-sm py-1.5 pl-2 pr-2 text-sm outline-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-transparent data-[highlighted]:text-inherit"
                >
                  <span class="block truncate">{{ tag.name }}</span>
                </ComboboxItem>
                <div
                  v-if="showDelete(tag)"
                  class="flex shrink-0 items-center self-stretch py-1 pr-1"
                >
                  <button
                    type="button"
                    class="inline-flex shrink-0 rounded p-1 text-destructive outline-none hover:bg-destructive/10 focus-visible:ring-2 focus-visible:ring-ring"
                    title="Удалить тег"
                    aria-label="Удалить тег"
                    @click.stop.prevent="onDeleteClick(tag)"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M3 6h18" />
                      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                      <line x1="10" x2="10" y1="11" y2="17" />
                      <line x1="14" x2="14" y1="11" y2="17" />
                    </svg>
                  </button>
                </div>
              </div>
            </ComboboxViewport>
            <div
              v-if="allowCreateTag"
              class="shrink-0 border-t border-border bg-popover p-1"
              @pointerdown.stop
              @keydown.stop
              @keyup.stop
            >
              <template v-if="!addingNewTag">
                <button
                  type="button"
                  class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground"
                  @mousedown.prevent
                  @click="startAddingNewTag"
                >
                  <span class="text-base leading-none">+</span>
                  Добавить новый
                </button>
              </template>
              <template v-else>
                <div class="flex flex-col gap-2 p-1">
                  <Input
                    ref="newTagInputRef"
                    v-model="newTagName"
                    placeholder="Название тега"
                    class="h-8 text-sm"
                    maxlength="20"
                    :disabled="creatingTag"
                    @keydown.enter.prevent="createAndAddTag"
                  />
                  <div class="flex gap-1">
                    <Button
                      type="button"
                      size="sm"
                      variant="secondary"
                      class="flex-1"
                      :disabled="!newTagName.trim() || creatingTag"
                      @click="createAndAddTag"
                    >
                      {{ creatingTag ? '…' : 'Создать' }}
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      :disabled="creatingTag"
                      @click="cancelNewTag"
                    >
                      Отмена
                    </Button>
                  </div>
                </div>
              </template>
            </div>
          </ComboboxContent>
        </ComboboxPortal>
      </ComboboxRoot>
    </ClientOnly>
  </div>
</template>
