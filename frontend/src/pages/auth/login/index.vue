<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { Button, Input, Label, Card, CardContent, Separator, SiteLogo } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import SocialAuthButtons from '@/shared/ui/SocialAuthButtons.vue';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const remember = ref(true);
const resendMessage = ref<string | null>(null);
const resendLoading = ref(false);

const socialError = computed(() => {
  if (route.query.error === 'banned') return 'Аккаунт заблокирован. Обратитесь к администратору.';
  return null;
});

const emailVerified = computed(() => route.query.verified === '1');

const needsVerification = computed(() =>
  !!auth.error && auth.error.includes('подтвердить email'),
);

async function onResendVerification() {
  if (!email.value) return;
  resendLoading.value = true;
  resendMessage.value = null;
  try {
    const result = await auth.resendVerification(email.value);
    resendMessage.value = result.message;
  } catch {
    // ошибка уже в auth.error
  } finally {
    resendLoading.value = false;
  }
}

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
  resendMessage.value = null;
  try {
    await auth.login(email.value, password.value, remember.value);
    await router.push('/');
  } catch {
    // ошибка уже в auth.error
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
            <h1 class="text-2xl font-bold">Вход в аккаунт</h1>
            <p class="text-sm text-muted-foreground">
              Введите email и пароль для входа
            </p>
          </div>
          <Card>
            <CardContent class="pt-6">
              <form class="flex flex-col gap-4" @submit="onSubmit">
                <p v-if="emailVerified" class="rounded-md bg-primary/10 px-3 py-2 text-sm text-primary">
                  Email успешно подтверждён. Теперь вы можете войти.
                </p>
                <p v-if="auth.error || socialError" class="text-sm text-destructive">{{ auth.error || socialError }}</p>
                <div v-if="needsVerification && email" class="space-y-2">
                  <button
                    type="button"
                    class="text-sm font-medium text-primary underline-offset-4 hover:underline"
                    :disabled="resendLoading"
                    @click="onResendVerification"
                  >
                    {{ resendLoading ? 'Отправка...' : 'Отправить письмо повторно' }}
                  </button>
                  <p v-if="resendMessage" class="rounded-md bg-primary/10 px-3 py-2 text-sm text-primary">
                    {{ resendMessage }}
                  </p>
                </div>
                <div class="space-y-2">
                  <Label for="email">Email</Label>
                  <Input id="email" v-model="email" type="email" placeholder="you@example.com" required />
                </div>
                <div class="space-y-2">
                  <div class="flex items-center">
                    <Label for="password">Пароль</Label>
                    <RouterLink to="/forgot-password" class="ml-auto text-sm text-primary underline-offset-4 hover:underline">
                      Забыли пароль?
                    </RouterLink>
                  </div>
                  <Input id="password" v-model="password" type="password" required />
                </div>
                <label class="flex items-center gap-2 text-sm">
                  <input v-model="remember" type="checkbox" class="h-4 w-4 rounded border-input" />
                  <span>Запомнить меня</span>
                </label>
                <Button type="submit" class="w-full" :disabled="auth.loading">
                  {{ auth.loading ? 'Вход...' : 'Войти' }}
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
            Нет аккаунта?
            <RouterLink to="/register" class="font-medium text-primary underline-offset-4 hover:underline">
              Регистрация
            </RouterLink>
          </p>
        </div>
      </div>
    </div>
    <div class="relative hidden bg-muted lg:block">
      <div class="absolute inset-0 bg-gradient-to-br from-primary/20 via-background to-primary/5" />
      <div class="absolute inset-0 flex items-center justify-center p-12">
        <p class="max-w-md text-center text-lg text-muted-foreground">
          Присоединяйтесь к тысячам игроков. Находите гильдии, участвуйте в рейдах и следите за новостями мира MMORPG.
        </p>
      </div>
    </div>
  </div>
</template>
