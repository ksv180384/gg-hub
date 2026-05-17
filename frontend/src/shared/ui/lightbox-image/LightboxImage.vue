<script setup lang="ts">
import { ref, watch } from 'vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { Button } from '@/shared/ui';
import { cn } from '@/shared/lib/utils';

interface Props {
  src: string;
  alt: string;
  title?: string | null;
  /** Класс кнопки-обёртки превью */
  buttonClass?: string;
  /** Класс изображения превью */
  imgClass?: string;
  /** Класс модального изображения */
  lightboxImgClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
  title: null,
  buttonClass: '',
  imgClass: '',
  lightboxImgClass: '',
});

const open = ref(false);

function close() {
  open.value = false;
}

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') close();
}

watch(open, (v) => {
  if (typeof document === 'undefined') return;
  if (v) document.addEventListener('keydown', onEscape);
  else document.removeEventListener('keydown', onEscape);
});
</script>

<template>
  <button
    type="button"
    :title="props.title || undefined"
    :aria-label="props.title || 'Увеличить изображение'"
    :class="cn('cursor-pointer', props.buttonClass)"
    @click="open = true"
  >
    <img
      :src="props.src"
      :alt="props.alt"
      loading="lazy"
      decoding="async"
      :class="cn(props.imgClass)"
    >
  </button>

  <!-- Lightbox только на клиенте — Teleport + Transition при SSR дают hydration mismatch -->
  <ClientOnly>
    <Teleport to="body">
      <Transition name="lightbox">
        <div
          v-if="open"
          class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
          aria-modal="true"
          role="dialog"
          :aria-label="props.title || 'Просмотр изображения'"
          @click.self="close"
        >
          <button
            type="button"
            class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
            aria-label="Закрыть"
            title="Закрыть"
            @click="close"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
          <img
            :src="props.src"
            :alt="props.alt"
            class="max-h-[90vh] max-w-full select-none object-contain"
            :class="cn(props.lightboxImgClass)"
            @click.stop
          >
        </div>
      </Transition>
    </Teleport>
  </ClientOnly>
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

