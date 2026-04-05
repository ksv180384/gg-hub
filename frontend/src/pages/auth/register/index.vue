<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
} from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { Button, Input, Label, Card, CardContent, Separator, SiteLogo } from '@/shared/ui';
import SocialAuthButtons from '@/shared/ui/SocialAuthButtons.vue';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const consentAccepted = ref(false);
const consentError = ref('');

const legalModalType = ref<'privacy' | 'mailing' | null>(null);

const legalModalTitle = computed(() =>
  legalModalType.value === 'privacy'
    ? 'Согласие на обработку персональных данных'
    : 'Условия почтовых рассылок'
);

function openLegalModal(type: 'privacy' | 'mailing') {
  legalModalType.value = type;
}

function closeLegalModal() {
  legalModalType.value = null;
}

watch(consentAccepted, (v) => {
  if (v) consentError.value = '';
});

watch(
  () => auth.isAuthenticated,
  (isAuth) => {
    if (isAuth) router.replace('/');
  },
  { immediate: true }
);

onMounted(() => {
  auth.clearError();
});

async function onSubmit(e: Event) {
  e.preventDefault();
  if (password.value !== passwordConfirmation.value) {
    auth.setError('Пароли не совпадают');
    return;
  }
  if (!consentAccepted.value) {
    consentError.value = 'Подтвердите согласие на обработку данных и условия рассылок';
    return;
  }
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    await router.push('/');
  } catch {
    // ошибка в auth.error
  }
}
</script>

<template>
  <div class="grid min-h-svh lg:grid-cols-2">
    <div class="flex flex-col gap-4 p-6 md:p-10">
      <RouterLink to="/" class="flex items-center gap-2 font-medium md:justify-start">
        <SiteLogo :size="36" />
      </RouterLink>
      <div class="flex flex-1 items-center justify-center">
        <div class="w-full max-w-xs space-y-6 animate-in fade-in slide-in-from-left-4 duration-500">
          <div class="space-y-2 text-center">
            <h1 class="text-2xl font-bold">Регистрация</h1>
            <p class="text-sm text-muted-foreground">
              Создайте аккаунт, чтобы присоединиться к сообществу
            </p>
          </div>
          <Card>
            <CardContent class="pt-6">
              <form class="flex flex-col gap-4" @submit="onSubmit">
                <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>
                <div class="space-y-2">
                  <Label for="name">Имя или ник <span class="text-destructive" aria-hidden="true">*</span></Label>
                  <Input id="name" v-model="name" type="text" placeholder="PlayerName" required />
                </div>
                <div class="space-y-2">
                  <Label for="email">Email <span class="text-destructive" aria-hidden="true">*</span></Label>
                  <Input id="email" v-model="email" type="email" placeholder="you@example.com" required />
                </div>
                <div class="space-y-2">
                  <Label for="password">Пароль <span class="text-destructive" aria-hidden="true">*</span></Label>
                  <Input id="password" v-model="password" type="password" placeholder="Минимум 8 символов" required />
                </div>
                <div class="space-y-2">
                  <Label for="password_confirmation">
                    Подтверждение пароля <span class="text-destructive" aria-hidden="true">*</span>
                  </Label>
                  <Input
                    id="password_confirmation"
                    v-model="passwordConfirmation"
                    type="password"
                    placeholder="Повторите пароль"
                    required
                  />
                </div>

                <div class="space-y-2">
                  <div class="flex gap-3 rounded-md border border-input bg-background p-3">
                    <input
                      id="consent"
                      v-model="consentAccepted"
                      type="checkbox"
                      class="mt-1 h-4 w-4 shrink-0 rounded border-input accent-primary"
                      :aria-invalid="!!consentError"
                      aria-describedby="consent-hint"
                    />
                    <label id="consent-hint" for="consent" class="text-sm leading-snug text-muted-foreground">
                      <span class="text-destructive" aria-hidden="true">*</span>
                      Регистрируясь, я даю согласие на
                      <button
                        type="button"
                        class="inline text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-sm px-0.5"
                        @click="openLegalModal('privacy')"
                      >
                        обработку данных
                      </button>
                      и
                      <button
                        type="button"
                        class="inline text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-sm px-0.5"
                        @click="openLegalModal('mailing')"
                      >
                        условия почтовых рассылок
                      </button>
                      .
                    </label>
                  </div>
                  <p v-if="consentError" class="text-sm text-destructive">{{ consentError }}</p>
                </div>

                <Button type="submit" class="w-full" :disabled="auth.loading">
                  {{ auth.loading ? 'Регистрация...' : 'Зарегистрироваться' }}
                </Button>
                <div class="relative my-2">
                  <div class="absolute inset-0 flex items-center"><Separator /></div>
                  <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-card px-2 text-muted-foreground">или</span>
                  </div>
                </div>
                <SocialAuthButtons />
              </form>
            </CardContent>
          </Card>
          <p class="text-center text-sm text-muted-foreground">
            Уже есть аккаунт?
            <RouterLink to="/login" class="font-medium text-primary underline-offset-4 hover:underline">
              Войти
            </RouterLink>
          </p>
        </div>
      </div>
    </div>
    <div class="relative hidden bg-muted lg:block">
      <div class="absolute inset-0 bg-gradient-to-br from-primary/20 via-background to-primary/5" />
      <div class="absolute inset-0 flex items-center justify-center p-12">
        <p class="max-w-md text-center text-lg text-muted-foreground">
          Создавайте гильдии, публикуйте новости и находите тиммейтов для рейдов и PvP.
        </p>
      </div>
    </div>

    <DialogRoot
      :open="legalModalType !== null"
      @update:open="(v: boolean) => { if (!v) closeLegalModal(); }"
    >
      <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 max-h-[min(90vh,36rem)] w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 pt-14 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 sm:max-w-lg"
          :aria-describedby="undefined"
        >
          <button
            type="button"
            class="absolute right-4 top-4 z-10 rounded-sm p-1.5 text-muted-foreground opacity-80 ring-offset-background transition-opacity hover:opacity-100 hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
            aria-label="Закрыть"
            @click="closeLegalModal"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
          <DialogTitle class="text-lg font-semibold pr-10">
            {{ legalModalTitle }}
          </DialogTitle>
          <div
            v-if="legalModalType === 'privacy'"
            class="mt-4 max-h-[min(60vh,24rem)] overflow-y-auto text-sm text-muted-foreground space-y-3"
          >
            <p>
              Действуя свободно, своей волей и в своём интересе, а также подтверждая свою дееспособность, физическое
              лицо даёт своё согласие <strong>оператору интернет-сервиса GG Hub</strong> (платформа для игроков MMORPG и
              игровых гильдий, сайт gg-hub.ru и связанные субдомены; далее — <strong>Оператор</strong>) на обработку
              своих персональных данных на следующих условиях.
            </p>
            <p>
              Данное согласие даётся на обработку персональных данных как без использования средств автоматизации, так и
              с их использованием.
            </p>
            <p>
              Согласие даётся на обработку следующих персональных данных: имя или никнейм; адрес электронной почты;
              изображение профиля (аватар), если вы его указали; данные, доступные из профиля сторонней службы, с
              помощью которой выполняется вход на Сервис (например, при авторизации через Яндекс или ВКонтакте); иные
              данные, которые вы самостоятельно укажете в профиле или при использовании функций Сервиса.
            </p>
            <p>
              <strong>Цель обработки персональных данных:</strong> регистрация и ведение учётной записи на Сервисе;
              предоставление функций сайта (гильдии, персонажи, публикации, уведомления); улучшение работы и развитие
              Сервиса; проведение обезличенных исследований аудитории; направление информации о продуктах, услугах,
              новостях, акциях и предложениях Оператора по электронной почте (в части, на которую вы отдельно соглашаетесь
              в условиях рассылок).
            </p>
            <p>
              В ходе обработки с персональными данными могут совершаться действия: сбор; запись; систематизация;
              накопление; хранение; уточнение (обновление, изменение); извлечение; использование; передача (предоставление,
              доступ); обезличивание; блокирование; удаление; уничтожение.
            </p>
            <p>
              <strong>Третьи лица</strong>, которые могут обрабатывать персональные данные по поручению Оператора для
              достижения указанных целей, в том числе: операторы связи и хостинга; поставщики средств защиты
              инфраструктуры; сервисы транзакционной и сервисной почты; ООО «Яндекс» при использовании входа через
              аккаунт Яндекса; иные подрядчики по договорам с Оператором. Актуальный перечень и основания обработки
              уточняются в Политике конфиденциальности на сайте.
            </p>
            <p>
              Персональные данные обрабатываются до удаления учётной записи пользователем на Сервисе либо до отзыва
              согласия в пределах, допускаемых законом.
            </p>
            <p>
              Согласие может быть отозвано субъектом персональных данных путём направления заявления на адрес электронной
              почты службы поддержки, указанный в разделе «Контакты» / на странице сайта GG Hub.
            </p>
          </div>
          <div
            v-else-if="legalModalType === 'mailing'"
            class="mt-4 max-h-[min(60vh,24rem)] overflow-y-auto text-sm text-muted-foreground space-y-3"
          >
            <p>
              Настоящим, действуя свободно, своей волей и в своём интересе, даю согласие
              <strong>оператору интернет-сервиса GG Hub</strong> (платформа для игроков MMORPG и игровых гильдий, сайт
              gg-hub.ru и связанные субдомены; далее — <strong>Оператор</strong>) на получение мной сообщений
              информационного и/или рекламного характера (в том числе в форме рекламной рассылки) посредством
              электронной почты и (при наличии соответствующей технической возможности на Сервисе) иных электронных
              каналов доставки сообщений, о которых Оператор уведомит дополнительно.
            </p>
            <p>
              В этой связи выражаю согласие на обработку Оператором персональных данных:
              <strong>никнейм, адрес электронной почты</strong>; как без использования средств автоматизации, так и с их
              использованием, включая: сбор, запись, систематизацию, накопление, хранение, уточнение (обновление,
              изменение), извлечение, использование, передачу (предоставление, доступ), блокирование, удаление,
              уничтожение; в целях направления сообщений информационного и/или рекламного характера (в том числе в форме
              рекламной рассылки).
            </p>
            <p>
              <strong>Подтверждаю, что уведомлён о следующем:</strong>
            </p>
            <ul class="list-disc space-y-2 pl-5">
              <li>
                в любой момент в течение всего срока действия настоящего согласия я вправе отписаться от получения
                рассылки путём перехода по соответствующей ссылке в письме;
              </li>
              <li>
                при возникновении вопросов об отказе от рассылки я могу обратиться в службу поддержки Оператора,
                направив запрос на адрес электронной почты, указанный в разделе «Контакты» на сайте GG Hub.
              </li>
            </ul>
            <p>
              Настоящим гарантирую, что указанный мной при регистрации адрес электронной почты принадлежит мне, а при
              прекращении использования этого адреса обязуюсь проинформировать об этом Оператора. Указывая адрес
              электронной почты, принадлежащий третьему лицу, гарантирую, что получил согласие такого лица на обработку
              персональных данных в целях получения рекламно-информационных рассылок от Сервиса GG Hub, и обязуюсь
              предоставить копию такого согласия по запросу Оператора.
            </p>
            <p>
              Настоящее согласие действует с момента его выдачи до прекращения рассылки рекламно-информационных
              сообщений Оператором либо до дня отзыва настоящего согласия.
            </p>
            <p>
              Настоящее согласие может быть отозвано путём направления письменного заявления Оператору по реквизитам,
              размещённым на сайте, либо запроса на адрес электронной почты службы поддержки, указанный в разделе
              «Контакты».
            </p>
          </div>
        </DialogContent>
      </DialogPortal>
      </ClientOnly>
    </DialogRoot>
  </div>
</template>
