<script setup lang="ts">
import { watch, onBeforeUnmount, ref } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import { TextStyle, FontSize } from '@tiptap/extension-text-style';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import { Button, Tooltip, TooltipProvider, Input, Label } from '@/shared/ui';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import {
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import { cn } from '@/shared/lib/utils';
import { ImageWithSizeAlign } from './image-extended';
import { VideoEmbed, parseVideoUrl } from './video-embed';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    placeholder?: string;
    disabled?: boolean;
    class?: string;
  }>(),
  { placeholder: '', disabled: false }
);

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const linkUrl = ref('');
const imageUrl = ref('');
const currentFontSize = ref('default');

const linkDialogOpen = ref(false);
const imageDialogOpen = ref(false);
const videoDialogOpen = ref(false);
const linkUrlInput = ref('');
const imageUrlInput = ref('');
const videoUrlInput = ref('');
const videoUrlError = ref('');

const fontSizeOptions = [
  { value: 'default', label: 'Обычный' },
  { value: '12px', label: '12px' },
  { value: '14px', label: '14px' },
  { value: '16px', label: '16px' },
  { value: '18px', label: '18px' },
  { value: '20px', label: '20px' },
  { value: '24px', label: '24px' },
];

const editor = useEditor({
  content: props.modelValue || '',
  extensions: [
    // В StarterKit v3 уже есть link и underline — отключаем, ниже подключаем свои с настройками.
    StarterKit.configure({ link: false, underline: false }),
    Placeholder.configure({ placeholder: props.placeholder }),
    TextStyle,
    FontSize,
    TextAlign.configure({ types: ['paragraph', 'heading'] }),
    Underline,
    Link.configure({ openOnClick: false, HTMLAttributes: { target: '_blank', rel: 'noopener' } }),
    ImageWithSizeAlign,
    VideoEmbed.configure({ width: 640, height: 360 }),
  ],
  editable: !props.disabled,
  editorProps: {
    attributes: {
      class:
        'min-h-[280px] w-full px-3 py-2 text-sm outline-none prose prose-sm max-w-none dark:prose-invert [&_p]:my-2 first:[&_p]:mt-0 last:[&_p]:mb-0 [&_img]:max-w-full [&_img[data-wrap="left"]]:float-left [&_img[data-wrap="left"]]:mr-4 [&_img[data-wrap="left"]]:mb-2 [&_img[data-wrap="right"]]:float-right [&_img[data-wrap="right"]]:ml-4 [&_img[data-wrap="right"]]:mb-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:decoration-blue-600/40 [&_a]:underline-offset-2 hover:[&_a]:text-blue-700 dark:[&_a]:text-blue-400 dark:hover:[&_a]:text-blue-300 dark:[&_a]:decoration-blue-400/50 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_ol]:space-y-1 [&_li]:my-0.5 [&_.video-embed-wrapper]:my-4 [&_.video-embed-wrapper_iframe]:rounded-lg [&_.video-embed-wrapper_iframe]:max-w-full',
    },
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML());
  },
  onSelectionUpdate: ({ editor }) => {
    const attrs = editor.getAttributes('textStyle');
    currentFontSize.value = attrs.fontSize && fontSizeOptions.some((o) => o.value === attrs.fontSize)
      ? attrs.fontSize
      : 'default';
  },
});

watch(
  () => props.modelValue,
  (val) => {
    const html = val || '';
    if (editor.value && editor.value.getHTML() !== html) {
      editor.value.commands.setContent(html, false);
    }
  }
);

watch(
  () => props.disabled,
  (disabled) => {
    editor.value?.setEditable(!disabled);
  }
);

function openLinkDialog() {
  if (!editor.value || props.disabled) return;
  linkUrlInput.value = editor.value.getAttributes('link').href || 'https://';
  linkDialogOpen.value = true;
}

function submitLink() {
  if (!editor.value) return;
  const url = linkUrlInput.value.trim();
  if (url === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
  } else {
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }
  linkDialogOpen.value = false;
}

function removeLink() {
  if (!editor.value) return;
  editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
  linkDialogOpen.value = false;
}

function openImageDialog() {
  if (!editor.value || props.disabled) return;
  imageUrlInput.value = imageUrl.value || 'https://';
  imageDialogOpen.value = true;
}

function openVideoDialog() {
  if (!editor.value || props.disabled) return;
  videoUrlInput.value = '';
  videoUrlError.value = '';
  videoDialogOpen.value = true;
}

function extractSrcFromPastedIframe(pastedText: string): string | null {
  // Ищем src="..." или src='...' в iframe
  const m =
    pastedText.match(/<iframe[^>]*\ssrc\s*=\s*["']([^"']+)["']/i) ??
    pastedText.match(/src\s*=\s*["'](https?:\/\/[^"']+)["']/i);
  return m ? m[1] : null;
}

async function onVideoUrlPaste(e: ClipboardEvent) {
  e.preventDefault();
  e.stopPropagation();

  let pasted =
    e.clipboardData?.getData('text/html') ||
    e.clipboardData?.getData('text/plain');

  if (!pasted && navigator.clipboard?.readText) {
    try {
      pasted = await navigator.clipboard.readText();
    } catch {
      // clipboard API недоступен
    }
  }

  if (pasted) {
    const src = extractSrcFromPastedIframe(pasted);
    if (src && (src.includes('youtube') || src.includes('vk.com'))) {
      videoUrlInput.value = src;
      videoUrlError.value = '';
    } else {
      // Не iframe — вставляем как есть (отменяем preventDefault уже поздно, вставляем вручную)
      videoUrlInput.value = pasted;
    }
  }
}

function submitVideo() {
  if (!editor.value) return;
  const url = videoUrlInput.value.trim();
  if (!url) return;
  const parsed = parseVideoUrl(url);
  if (!parsed) {
    videoUrlError.value = 'Неверная ссылка. Поддерживаются YouTube и VK.';
    return;
  }
  videoUrlError.value = '';
  editor.value.chain().focus().setVideoEmbed({ src: url }).run();
  videoDialogOpen.value = false;
}

function submitImage() {
  if (!editor.value) return;
  const url = imageUrlInput.value.trim();
  if (!url) return;
  imageUrl.value = url;
  editor.value.chain().focus().setImage({ src: url }).run();
  imageDialogOpen.value = false;
}

function setImageWidth(width: string) {
  if (!editor.value || props.disabled) return;
  editor.value.chain().focus().updateAttributes('image', { width }).run();
}

function setImageAlign(align: 'left' | 'center' | 'right') {
  if (!editor.value || props.disabled) return;
  editor.value.chain().focus().updateAttributes('image', { align }).run();
}

function setImageWrap(wrap: 'none' | 'left' | 'right') {
  if (!editor.value || props.disabled) return;
  editor.value.chain().focus().updateAttributes('image', { wrap }).run();
}

function setFontSize(size: string) {
  if (!editor.value || props.disabled) return;
  currentFontSize.value = size;
  if (size === 'default') {
    editor.value.chain().focus().unsetFontSize().run();
  } else {
    editor.value.chain().focus().setFontSize(size).run();
  }
}

onBeforeUnmount(() => {
  editor.value?.destroy();
});
</script>

<template>
  <div
    :class="
      cn(
        'rounded-md border border-input bg-background text-sm shadow-sm focus-within:ring-1 focus-within:ring-ring',
        disabled && 'cursor-not-allowed opacity-60',
        props.class
      )
    "
  >
<TooltipProvider>
    <div
      v-if="editor"
      class="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/30 px-1 py-1"
    >
      <!-- Размер шрифта -->
      <SelectRoot
        :model-value="currentFontSize"
        :disabled="disabled"
        @update:model-value="setFontSize"
      >
        <SelectTrigger
          class="h-7 w-[100px] border-0 bg-transparent px-2 shadow-none hover:bg-muted"
          aria-label="Размер шрифта"
          title="Размер шрифта"
        >
          <SelectValue placeholder="Шрифт" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="opt in fontSizeOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </SelectItem>
        </SelectContent>
      </SelectRoot>

      <span class="mx-0.5 w-px self-stretch bg-border" aria-hidden="true" />

      <Tooltip content="Жирный" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('bold') }"
          @click="editor.chain().focus().toggleBold().run()"
        >
          <span class="font-bold">Ж</span>
        </Button>
      </Tooltip>
      <Tooltip content="Курсив" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0 italic"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('italic') }"
          @click="editor.chain().focus().toggleItalic().run()"
        >
          <span>К</span>
        </Button>
      </Tooltip>
      <Tooltip content="Подчёркивание" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0 underline"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('underline') }"
          @click="editor.chain().focus().toggleUnderline().run()"
        >
          <span>Ч</span>
        </Button>
      </Tooltip>
      <Tooltip content="Зачёркивание" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0 line-through"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('strike') }"
          @click="editor.chain().focus().toggleStrike().run()"
        >
          <span>S</span>
        </Button>
      </Tooltip>
      <Tooltip content="Вставить ссылку" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('link') }"
          @click="openLinkDialog"
        >
          <span class="text-xs">🔗</span>
        </Button>
      </Tooltip>

      <span class="mx-0.5 w-px self-stretch bg-border" aria-hidden="true" />

      <!-- Выравнивание текста -->
      <Tooltip content="Выравнивание по левому краю" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive({ textAlign: 'left' }) }"
          @click="editor.chain().focus().setTextAlign('left').run()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M3 12h14"/><path d="M3 18h10"/></svg>
        </Button>
      </Tooltip>
      <Tooltip content="Выравнивание по центру" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive({ textAlign: 'center' }) }"
          @click="editor.chain().focus().setTextAlign('center').run()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M5 12h14"/><path d="M7 18h10"/></svg>
        </Button>
      </Tooltip>
      <Tooltip content="Выравнивание по правому краю" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive({ textAlign: 'right' }) }"
          @click="editor.chain().focus().setTextAlign('right').run()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M7 12h14"/><path d="M11 18h10"/></svg>
        </Button>
      </Tooltip>

      <span class="mx-0.5 w-px self-stretch bg-border" aria-hidden="true" />

      <Tooltip content="Маркированный список" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('bulletList') }"
          @click="editor.chain().focus().toggleBulletList().run()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </Button>
      </Tooltip>
      <Tooltip content="Нумерованный список" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('orderedList') }"
          @click="editor.chain().focus().toggleOrderedList().run()"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
        </Button>
      </Tooltip>
      <Tooltip content="Заголовок 2" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          :class="{ 'bg-muted': editor.isActive('heading', { level: 2 }) }"
          @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        >
          <span class="text-xs font-semibold">H2</span>
        </Button>
      </Tooltip>

      <span class="mx-0.5 w-px self-stretch bg-border" aria-hidden="true" />

      <Tooltip content="Вставить изображение по ссылке" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          @click="openImageDialog"
        >
          <span class="text-xs">🖼</span>
        </Button>
      </Tooltip>
      <Tooltip content="Вставить видео (YouTube, VK)" side="bottom">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          :disabled="disabled"
          @click="openVideoDialog"
        >
          <span class="text-xs">▶</span>
        </Button>
      </Tooltip>

      <!-- Управление изображением (видно при выделении изображения) -->
      <template v-if="editor.isActive('image')">
        <Tooltip content="Размер: 25%" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 px-1 text-xs"
            :disabled="disabled"
            @click="setImageWidth('25%')"
          >
            25%
          </Button>
        </Tooltip>
        <Tooltip content="Размер: 50%" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 px-1 text-xs"
            :disabled="disabled"
            @click="setImageWidth('50%')"
          >
            50%
          </Button>
        </Tooltip>
        <Tooltip content="Размер: 75%" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 px-1 text-xs"
            :disabled="disabled"
            @click="setImageWidth('75%')"
          >
            75%
          </Button>
        </Tooltip>
        <Tooltip content="Размер: 100%" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 px-1 text-xs"
            :disabled="disabled"
            @click="setImageWidth('100%')"
          >
            100%
          </Button>
        </Tooltip>
        <Tooltip content="Изображение по левому краю" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            :disabled="disabled"
            :class="{ 'bg-muted': editor.getAttributes('image').align === 'left' }"
            @click="setImageAlign('left')"
          >
            <span class="text-xs">◀</span>
          </Button>
        </Tooltip>
        <Tooltip content="Изображение по центру" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            :disabled="disabled"
            :class="{ 'bg-muted': editor.getAttributes('image').align === 'center' }"
            @click="setImageAlign('center')"
          >
            <span class="text-xs">◆</span>
          </Button>
        </Tooltip>
        <Tooltip content="Изображение по правому краю" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            :disabled="disabled"
            :class="{ 'bg-muted': editor.getAttributes('image').align === 'right' }"
            @click="setImageAlign('right')"
          >
            <span class="text-xs">▶</span>
          </Button>
        </Tooltip>
        <span class="mx-0.5 w-px self-stretch bg-border" aria-hidden="true" />
        <Tooltip content="Картинка справа, текст обтекает слева. Совет: поставьте картинку перед абзацем с текстом." side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 px-1 text-xs"
            :disabled="disabled"
            :class="{ 'bg-muted': editor.getAttributes('image').wrap === 'right' }"
            @click="setImageWrap('right')"
          >
            Справа
          </Button>
        </Tooltip>
        <Tooltip content="Картинка слева, текст обтекает справа. Совет: поставьте картинку перед абзацем с текстом." side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 px-1 text-xs"
            :disabled="disabled"
            :class="{ 'bg-muted': editor.getAttributes('image').wrap === 'left' }"
            @click="setImageWrap('left')"
          >
            Слева
          </Button>
        </Tooltip>
        <Tooltip content="Без обтекания текстом" side="bottom">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 px-1 text-xs"
            :disabled="disabled"
            :class="{ 'bg-muted': editor.getAttributes('image').wrap === 'none' }"
            @click="setImageWrap('none')"
          >
            Нет
          </Button>
        </Tooltip>
      </template>
    </div>
    </TooltipProvider>
    <EditorContent :editor="editor" />

    <!-- Диалог добавления ссылки -->
    <DialogRoot v-model:open="linkDialogOpen">
      <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            Вставить ссылку
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Введите URL страницы. Оставьте поле пустым, чтобы убрать ссылку с выделенного текста.
          </DialogDescription>
          <div class="grid gap-4 py-2">
            <div class="grid gap-2">
              <Label for="link-url">Адрес (URL)</Label>
              <Input
                id="link-url"
                v-model="linkUrlInput"
                type="url"
                placeholder="https://"
                class="w-full"
                @keydown.enter.prevent="submitLink"
              />
            </div>
          </div>
          <div class="flex flex-wrap justify-end gap-2 pt-2">
            <Button variant="outline" @click="linkDialogOpen = false">
              Отмена
            </Button>
            <Button
              v-if="editor?.getAttributes('link').href"
              variant="ghost"
              class="text-destructive hover:bg-destructive/10 hover:text-destructive"
              @click="removeLink"
            >
              Удалить ссылку
            </Button>
            <Button @click="submitLink">
              Применить
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
      </ClientOnly>
    </DialogRoot>

    <!-- Диалог добавления изображения -->
    <DialogRoot v-model:open="imageDialogOpen">
      <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            Вставить изображение
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Введите URL изображения. Поддерживаются ссылки на картинки в интернете.
          </DialogDescription>
          <div class="grid gap-4 py-2">
            <div class="grid gap-2">
              <Label for="image-url">Адрес изображения (URL)</Label>
              <Input
                id="image-url"
                v-model="imageUrlInput"
                type="url"
                placeholder="https://"
                class="w-full"
                @keydown.enter.prevent="submitImage"
              />
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <Button variant="outline" @click="imageDialogOpen = false">
              Отмена
            </Button>
            <Button :disabled="!imageUrlInput.trim()" @click="submitImage">
              Вставить
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
      </ClientOnly>
    </DialogRoot>

    <!-- Диалог добавления видео -->
    <DialogRoot v-model:open="videoDialogOpen">
      <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            Вставить видео
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground">
            Вставьте ссылку на видео с YouTube или ВКонтакте.
          </DialogDescription>
          <div class="grid gap-4 py-2">
            <div class="grid gap-2">
              <Label for="video-url">Ссылка на видео</Label>
              <input
                id="video-url"
                v-model="videoUrlInput"
                type="text"
                placeholder="https://www.youtube.com/watch?v=... или https://vk.com/video..."
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring md:text-sm"
                @keydown.enter.prevent="submitVideo"
                @paste="onVideoUrlPaste"
              >
              <p v-if="videoUrlError" class="text-sm text-destructive">
                {{ videoUrlError }}
              </p>
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <Button variant="outline" @click="videoDialogOpen = false">
              Отмена
            </Button>
            <Button :disabled="!videoUrlInput.trim()" @click="submitVideo">
              Вставить
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
      </ClientOnly>
    </DialogRoot>
  </div>
</template>
