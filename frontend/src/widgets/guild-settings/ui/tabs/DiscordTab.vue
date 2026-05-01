<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button, Input, Label, Spinner, Tooltip } from '@/shared/ui';
import {
  DISCORD_NOTIFICATION_LABELS,
  isValidDiscordWebhookUrl,
  type DiscordNotificationKey,
} from '@/features/guild-settings';

const props = defineProps<{
  webhookUrl: string;
  webhookError: string | null;
  notifications: Record<DiscordNotificationKey, boolean>;
  saving: boolean;
  /** Какая галочка сейчас уходит на сервер (остальные заблокированы). */
  savingNotificationKey: DiscordNotificationKey | null;
}>();

const emit = defineEmits<{
  (e: 'update:webhookUrl', value: string): void;
  (e: 'save'): void;
  (e: 'notificationChange', key: DiscordNotificationKey, value: boolean): void;
}>();

/** Слоты под скриншоты инструкции. Изображения добавляются по этим путям позже. */
const failedImages = ref<Record<string, boolean>>({});
function onImageError(src: string) {
  failedImages.value = { ...failedImages.value, [src]: true };
}

const INSTRUCTION_STEPS: { src: string; alt: string; text: string }[] = [
  {
    src: '/discord-instruction/step-1.png',
    alt: 'Шаг 1: открыть настройки сервера',
    text: 'Зайдите в настройки сервера.',
  },
  {
    src: '/discord-instruction/step-2.png',
    alt: 'Шаг 2: открыть «Интеграции»',
    text: 'Выберите пункт «Интеграция», потом «Посмотреть вебхуки».',
  },
  {
    src: '/discord-instruction/step-3.png',
    alt: 'Шаг 3: создать новый вебхук',
    text: 'Создайте новый вебхук.',
  },
  {
    src: '/discord-instruction/step-4.png',
    alt: 'Шаг 4: настроить вебхук',
    text: 'Выберите аватар вебхуку, задайте название, канал в котором хотите получать оповещения.',
  },
  {
    src: '/discord-instruction/step-5.png',
    alt: 'Шаг 5: скопировать URL',
    text: 'Скопируйте URL вебхука и добавьте его на сайт и сохраните. Выберите оповещения, которые хотите получать с сайта.',
  },
];

/** Текущая клиентская валидация URL (строгая проверка только на непустом значении). */
const isUrlValid = computed(() => {
  const url = props.webhookUrl.trim();
  return url === '' || isValidDiscordWebhookUrl(url);
});

const lightboxSrc = ref<string | null>(null);
const lightboxAlt = ref('');
function openLightbox(src: string, alt: string) {
  lightboxSrc.value = src;
  lightboxAlt.value = alt;
}
function closeLightbox() {
  lightboxSrc.value = null;
  lightboxAlt.value = '';
}

const notificationCheckboxesDisabled = computed(
  () => props.webhookUrl.trim() === '' || props.savingNotificationKey !== null
);

const eventStartingHint =
  'Если нужно отключить это уведомление только для конкретного события, откройте календарь событий гильдии, перейдите в создание или редактирование события и снимите галочку «Отправлять оповещение в Discord».';

const instructionOpen = ref(false);
function toggleInstruction() {
  instructionOpen.value = !instructionOpen.value;
}
</script>

<template>
  <!-- Один корень: у компонента с несколькими корнями v-show родителя не применяется ко всем узлам,
       из‑за этого вкладка Discord была видна на всех вкладках (Card + Teleport). -->
  <div class="min-w-0">
    <div class="mb-6 min-w-0 space-y-6">
        <div class="space-y-3">
          <p class="text-sm text-muted-foreground">
            Заполните URL вебхука, отметьте нужные оповещения и нажмите «Сохранить».
          </p>

          <div class="space-y-2">
            <div class="flex max-w-md flex-wrap items-center gap-2">
              <span id="discord-instruction-toggle" class="text-sm font-medium text-foreground">Инструкция</span>
              <button
                type="button"
                class="inline-flex shrink-0 rounded-full p-0.5 text-yellow-500 outline-none transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2"
                :aria-expanded="instructionOpen"
                aria-controls="discord-instruction-panel"
                aria-labelledby="discord-instruction-toggle"
                title="Показать или скрыть инструкцию"
                @click="toggleInstruction"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="22"
                  height="22"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="text-yellow-500"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="10" class="fill-amber-400/25" stroke="currentColor" />
                  <path d="M12 16v-4" />
                  <path d="M12 8h.01" />
                </svg>
                <span class="sr-only">{{ instructionOpen ? 'Скрыть инструкцию' : 'Показать инструкцию' }}</span>
              </button>
            </div>

            <div
              class="grid transition-[grid-template-rows] duration-300 ease-in-out motion-reduce:transition-none"
              :class="instructionOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
            >
              <div class="min-h-0 overflow-hidden">
                <div
                  id="discord-instruction-panel"
                  role="region"
                  aria-labelledby="discord-instruction-toggle"
                  :aria-hidden="!instructionOpen"
                  :inert="!instructionOpen"
                >
                  <p class="mb-4 text-sm text-foreground">
                    Discord вебхуки позволяют отправлять оповещения с сайта в ваш discord
                  </p>
                  <h3 class="mb-3 text-sm font-medium text-foreground underline decoration-border underline-offset-2">
                    Как настроить вебхук в Discord
                  </h3>
                  <ol class="list-decimal space-y-4 pl-5 text-sm text-foreground">
                    <li v-for="step in INSTRUCTION_STEPS" :key="step.src" class="space-y-2">
                      <p>{{ step.text }}</p>
                      <button
                        type="button"
                        class="group block w-full max-w-md overflow-hidden rounded-md border border-border bg-muted/30 transition-colors hover:border-primary focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :aria-label="failedImages[step.src] ? `Изображение ещё не добавлено: ${step.alt}` : `Открыть в полном размере: ${step.alt}`"
                        :disabled="failedImages[step.src]"
                        @click="!failedImages[step.src] && openLightbox(step.src, step.alt)"
                      >
                        <img
                          v-if="!failedImages[step.src]"
                          :src="step.src"
                          :alt="step.alt"
                          loading="lazy"
                          class="block h-auto w-full select-none object-contain transition-transform group-hover:scale-[1.01]"
                          @error="onImageError(step.src)"
                        >
                        <div
                          v-else
                          class="flex h-32 items-center justify-center px-3 text-center text-xs text-muted-foreground"
                          aria-hidden="true"
                        >
                          Изображение появится позже
                        </div>
                      </button>
                    </li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-8">
          <div class="min-w-0 space-y-3">
              <div class="space-y-2">
                <Label for="discord-webhook-url">URL вебхука Discord <span aria-hidden="true">*</span></Label>
                <Input
                  id="discord-webhook-url"
                  type="url"
                  required
                  autocomplete="off"
                  spellcheck="false"
                  placeholder="https://discord.com/api/webhooks/..."
                  class="w-full font-mono text-xs sm:text-sm"
                  :model-value="webhookUrl"
                  :aria-invalid="!isUrlValid || webhookError !== null"
                  @update:model-value="emit('update:webhookUrl', String($event))"
                />
                <p v-if="webhookError" class="text-sm text-destructive">{{ webhookError }}</p>
                <p v-else-if="!isUrlValid" class="text-sm text-destructive">
                  Укажите корректный URL Discord-вебхука вида https://discord.com/api/webhooks/&lt;id&gt;/&lt;token&gt;.
                </p>
                <p v-else class="text-xs text-muted-foreground">
                  Чтобы отключить оповещения — очистите поле и нажмите «Сохранить».
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <Button :disabled="saving || !isUrlValid" @click="emit('save')">
                  {{ saving ? 'Сохранение…' : 'Сохранить' }}
                </Button>
              </div>
          </div>

          <div class="min-w-0 space-y-3">
              <h3 class="text-sm font-medium text-foreground">Оповещения</h3>
              <p class="text-xs text-muted-foreground">
                Отметьте события, о которых вы хотите получать оповещения в Discord.
              </p>

              <ul class="space-y-2">
                <li
                  v-for="item in DISCORD_NOTIFICATION_LABELS"
                  :key="item.key"
                  class="flex items-start gap-2"
                >
                  <span
                    class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center"
                    :aria-busy="savingNotificationKey === item.key"
                  >
                    <Spinner
                      v-if="savingNotificationKey === item.key"
                      class="h-4 w-4 shrink-0 text-muted-foreground"
                      aria-hidden="true"
                    />
                    <input
                      v-else
                      :id="`discord-notif-${item.key}`"
                      type="checkbox"
                      class="h-4 w-4 rounded border-input"
                      :checked="notifications[item.key]"
                      :disabled="notificationCheckboxesDisabled"
                      @change="
                        emit(
                          'notificationChange',
                          item.key,
                          ($event.target as HTMLInputElement).checked
                        )
                      "
                    >
                  </span>
                  <div class="flex min-w-0 flex-1 items-start justify-between gap-2">
                    <Label
                      :for="savingNotificationKey === item.key ? undefined : `discord-notif-${item.key}`"
                      class="cursor-pointer font-normal leading-snug"
                      :class="{ 'opacity-60 pointer-events-none': notificationCheckboxesDisabled }"
                    >
                      {{ item.label }}
                    </Label>
                    <Tooltip
                      v-if="item.key === 'discord_notify_event_starting'"
                      :content="eventStartingHint"
                      side="top"
                      class="max-w-sm text-left leading-relaxed"
                    >
                      <button
                        type="button"
                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :aria-label="eventStartingHint"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="14"
                          height="14"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          aria-hidden="true"
                        >
                          <circle cx="12" cy="12" r="10" />
                          <path d="M12 16v-4" />
                          <path d="M12 8h.01" />
                        </svg>
                      </button>
                    </Tooltip>
                  </div>
                </li>
              </ul>

              <p v-if="webhookUrl.trim() === ''" class="text-xs text-muted-foreground">
                Чтобы включить оповещения, укажите URL вебхука и нажмите «Сохранить» — после этого галочки сохраняются сразу при изменении.
              </p>
          </div>
        </div>
    </div>

  <Teleport to="body">
    <Transition name="discord-lightbox">
      <div
        v-if="lightboxSrc"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
        aria-modal="true"
        role="dialog"
        aria-label="Просмотр изображения"
        @click.self="closeLightbox"
      >
        <button
          type="button"
          class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
          aria-label="Закрыть"
          @click="closeLightbox"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
        <img
          :src="lightboxSrc"
          :alt="lightboxAlt"
          class="max-h-[90vh] max-w-full select-none object-contain"
          @click.stop
        >
      </div>
    </Transition>
  </Teleport>
  </div>
</template>

<style scoped>
.discord-lightbox-enter-active,
.discord-lightbox-leave-active {
  transition: opacity 0.2s ease;
}
.discord-lightbox-enter-from,
.discord-lightbox-leave-to {
  opacity: 0;
}
.discord-lightbox-enter-active img,
.discord-lightbox-leave-active img {
  transition: transform 0.2s ease;
}
.discord-lightbox-enter-from img,
.discord-lightbox-leave-to img {
  transform: scale(0.95);
}

</style>
