<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { Button, Input, Label } from '@/shared/ui';
import SocialAuthButtons from '@/shared/ui/SocialAuthButtons.vue';
import { useAuthStore } from '@/stores/auth';
import AuthHomeLink from './AuthHomeLink.vue';
import AuthFormBlock from './AuthFormBlock.vue';
import RegistrationConsent from './RegistrationConsent.vue';

const auth = useAuthStore();
const router = useRouter();
const name = ref('');
const email = ref('');
const password = ref('');
const confirmation = ref('');
const consent = ref(false);
const consentError = ref('');
const verificationSent = ref(false);
const resendMessage = ref('');

onMounted(() => auth.clearError());

async function submit(event: Event) {
  event.preventDefault();
  consentError.value = '';
  if (password.value !== confirmation.value) {
    auth.setError('Пароли не совпадают');
    return;
  }
  if (!consent.value) {
    consentError.value = 'Подтвердите согласие с условиями сервиса';
    return;
  }
  try {
    const data = await auth.register({
      name: name.value.trim() || undefined,
      email: email.value,
      password: password.value,
      password_confirmation: confirmation.value,
    });
    if (data.requires_email_verification) {
      verificationSent.value = true;
      return;
    }
    await router.push('/');
  } catch {
    // Ошибка хранится в auth.error.
  }
}

async function resend() {
  try {
    resendMessage.value = (await auth.resendVerification(email.value)).message;
  } catch {
    // Ошибка хранится в auth.error.
  }
}
</script>

<template>
  <AuthFormBlock
    :title="verificationSent ? 'Подтвердите email' : 'Создать аккаунт'"
    :subtitle="verificationSent ? `Письмо отправлено на ${email}` : 'Присоединяйтесь к сообществу GG Hub'"
    @submit="submit"
  >
    <template v-if="verificationSent">
      <p class="text-center text-sm text-white/70">Перейдите по ссылке в письме, чтобы активировать аккаунт.</p>
      <p v-if="resendMessage" class="auth-alert">{{ resendMessage }}</p>
      <Button type="button" variant="outline" class="w-full" :disabled="auth.loading" @click="resend">
        Отправить письмо повторно
      </Button>
    </template>
    <template v-else>
      <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>
      <div class="auth-field"><Label for="register-name">Имя</Label><Input id="register-name" v-model="name" placeholder="Имя или никнейм" /></div>
      <div class="auth-field"><Label for="register-email">Email</Label><Input id="register-email" v-model="email" type="email" placeholder="Email *" required /></div>
      <div class="auth-field"><Label for="register-password">Пароль</Label><Input id="register-password" v-model="password" type="password" placeholder="Пароль, минимум 8 символов *" required /></div>
      <div class="auth-field"><Label for="register-confirmation">Повторите пароль</Label><Input id="register-confirmation" v-model="confirmation" type="password" placeholder="Повторите пароль *" required /></div>
      <RegistrationConsent v-model="consent" />
      <p v-if="consentError" class="text-sm text-destructive">{{ consentError }}</p>
      <Button type="submit" class="auth-submit w-full" :disabled="auth.loading">
        {{ auth.loading ? 'Регистрация...' : 'Зарегистрироваться' }}
      </Button>
      <div class="auth-divider"><span>или</span></div>
      <SocialAuthButtons />
    </template>
    <template #footer>
      <p class="mt-6 text-center text-sm text-white/65">
        <RouterLink
          v-if="verificationSent"
          to="/login"
        >
          Перейти ко входу
        </RouterLink>
        <template v-else>
          <span>Уже есть аккаунт? </span>
          <RouterLink to="/login">
            Войти
          </RouterLink>
        </template>
      </p>
      <AuthHomeLink />
    </template>
  </AuthFormBlock>
</template>
