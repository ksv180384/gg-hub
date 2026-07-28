<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { Button, Input, Label } from '@/shared/ui';
import SocialAuthButtons from '@/shared/ui/SocialAuthButtons.vue';
import { useAuthStore } from '@/stores/auth';
import AuthCheckbox from './AuthCheckbox.vue';
import AuthHomeLink from './AuthHomeLink.vue';
import AuthFormBlock from './AuthFormBlock.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const email = ref('');
const password = ref('');
const remember = ref(true);
const resendMessage = ref<string | null>(null);
const resendLoading = ref(false);

const socialError = computed(() => {
  return route.query.error === 'banned'
    ? 'Аккаунт заблокирован. Обратитесь к администратору.'
    : null;
});
const emailVerified = computed(() => route.query.verified === '1');
const needsVerification = computed(() => {
  return /подтвердить email|подтвержден/.test(auth.error?.toLowerCase() ?? '');
});

onMounted(() => auth.clearError());

async function submit(event: Event) {
  event.preventDefault();
  resendMessage.value = null;
  try {
    await auth.login(email.value, password.value, remember.value);
    await router.push('/');
  } catch {
    // Ошибка хранится в auth.error.
  }
}

async function resend() {
  if (!email.value) return;
  resendLoading.value = true;
  try {
    resendMessage.value = (await auth.resendVerification(email.value)).message;
  } catch {
    // Ошибка хранится в auth.error.
  } finally {
    resendLoading.value = false;
  }
}
</script>

<template>
  <AuthFormBlock
    title="Войти в GG-Hub"
    subtitle="Войди в аккаунт и продолжи игру"
    @submit="submit"
  >
    <p v-if="emailVerified" class="auth-alert">
      Email успешно подтверждён. Теперь вы можете войти.
    </p>
    <p v-if="auth.error || socialError" class="text-sm text-destructive">
      {{ auth.error || socialError }}
    </p>
    <button
      v-if="needsVerification && email"
      type="button"
      class="auth-link text-sm"
      :disabled="resendLoading"
      @click="resend"
    >
      {{ resendLoading ? 'Отправка...' : 'Отправить письмо повторно' }}
    </button>
    <p v-if="resendMessage" class="auth-alert">{{ resendMessage }}</p>

    <div class="auth-field">
      <Label for="email">Email</Label>
      <Input id="email" v-model="email" type="email" placeholder="Логин или email" required />
    </div>
    <div class="auth-field">
      <Label for="password">Пароль</Label>
      <Input id="password" v-model="password" type="password" placeholder="Пароль" required />
    </div>

    <div class="flex items-center justify-between gap-4 text-sm">
      <AuthCheckbox v-model="remember">Запомнить меня</AuthCheckbox>
      <RouterLink to="/forgot-password">Забыли пароль?</RouterLink>
    </div>

    <Button type="submit" class="auth-submit w-full" :disabled="auth.loading">
      {{ auth.loading ? 'Вход...' : 'Войти' }}
    </Button>
    <div class="auth-divider"><span>или</span></div>
    <SocialAuthButtons />

    <template #footer>
      <p class="mt-6 text-center text-sm text-white/65">
        Нет аккаунта? <RouterLink to="/register">Создать аккаунт</RouterLink>
      </p>
      <AuthHomeLink />
    </template>
  </AuthFormBlock>
</template>
