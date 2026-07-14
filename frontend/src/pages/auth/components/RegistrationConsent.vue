<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import AuthCheckbox from './AuthCheckbox.vue';

type LegalModalType = 'privacy' | 'mailing' | 'beta';

const accepted = defineModel<boolean>({ required: true });
const legalModalType = ref<LegalModalType | null>(null);

const legalModalTitle = computed(() => {
  if (legalModalType.value === 'privacy') return 'Согласие на обработку персональных данных';
  if (legalModalType.value === 'mailing') return 'Условия почтовых рассылок';
  return 'Бета-версия сервиса';
});

function openLegalModal(type: LegalModalType) {
  legalModalType.value = type;
}
</script>

<template>
  <div class="registration-consent">
    <AuthCheckbox id="consent" v-model="accepted">
      <span class="text-destructive" aria-hidden="true">*</span>
      Регистрируясь, я даю согласие на
      <button type="button" @click.prevent="openLegalModal('privacy')">обработку данных</button>
      и
      <button type="button" @click.prevent="openLegalModal('mailing')">условия почтовых рассылок</button>,
      а также принимаю
      <button type="button" @click.prevent="openLegalModal('beta')">условия тестового режима</button>.
    </AuthCheckbox>

    <DialogRoot :open="legalModalType !== null" @update:open="(open) => { if (!open) legalModalType = null; }">
      <ClientOnly>
        <DialogPortal>
          <DialogOverlay class="fixed inset-0 z-50 bg-black/80" />
          <DialogContent class="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[min(32rem,calc(100%-2rem))] -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 pt-14 shadow-xl">
            <button class="absolute right-4 top-4 text-muted-foreground" type="button" aria-label="Закрыть" @click="legalModalType = null">✕</button>
            <DialogTitle class="pr-8 text-lg font-semibold">{{ legalModalTitle }}</DialogTitle>

            <div v-if="legalModalType === 'privacy'" class="legal-copy">
              <p>Действуя свободно, своей волей и в своём интересе, пользователь даёт согласие <strong>оператору интернет-сервиса GG Hub</strong> на обработку персональных данных.</p>
              <p>Согласие распространяется на имя или никнейм, адрес электронной почты, изображение профиля, данные сторонней службы авторизации и сведения, самостоятельно указанные пользователем.</p>
              <p><strong>Цели обработки:</strong> регистрация и ведение учётной записи, предоставление функций сервиса, улучшение работы GG Hub и направление согласованных информационных сообщений.</p>
              <p>Согласие действует до удаления учётной записи или его отзыва через службу поддержки.</p>
            </div>

            <div v-else-if="legalModalType === 'mailing'" class="legal-copy">
              <p>Пользователь даёт <strong>оператору интернет-сервиса GG Hub</strong> согласие на получение информационных и рекламных сообщений по электронной почте.</p>
              <p>Для рассылок обрабатываются никнейм и адрес электронной почты.</p>
              <p>От рассылки можно отказаться в любой момент по ссылке в письме или через службу поддержки GG Hub.</p>
            </div>

            <div v-else class="legal-copy">
              <p>GG Hub работает в <strong>тестовом режиме (бета)</strong>. Функции, интерфейс и стабильность сервиса продолжают улучшаться.</p>
              <p>Иногда возможны временные ошибки, ограничения, технические работы или очистка тестовых данных.</p>
              <p>Если что-то пошло не так, напишите в поддержку: <strong>support@gg-hub.ru</strong>.</p>
            </div>
          </DialogContent>
        </DialogPortal>
      </ClientOnly>
    </DialogRoot>
  </div>
</template>

<style scoped>
.registration-consent {
  display: flex;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.375rem;
  background: rgba(255, 255, 255, 0.025);
}

.registration-consent button {
  color: #ffc42e;
  text-decoration-thickness: 1px;
  text-underline-offset: 3px;
}

.registration-consent button:hover {
  text-decoration-line: underline;
}

.legal-copy {
  max-height: min(60vh, 24rem);
  margin-top: 1rem;
  overflow-y: auto;
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
  line-height: 1.6;
}

.legal-copy > * + * {
  margin-top: 0.75rem;
}
</style>
