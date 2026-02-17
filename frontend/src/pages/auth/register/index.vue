<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { Button, Input, Label, Card, CardContent } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');

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
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">⚔</span>
        GG Hub
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
                  <Label for="name">Имя или ник</Label>
                  <Input id="name" v-model="name" type="text" placeholder="PlayerName" required />
                </div>
                <div class="space-y-2">
                  <Label for="email">Email</Label>
                  <Input id="email" v-model="email" type="email" placeholder="you@example.com" required />
                </div>
                <div class="space-y-2">
                  <Label for="password">Пароль</Label>
                  <Input id="password" v-model="password" type="password" placeholder="Минимум 8 символов" required />
                </div>
                <div class="space-y-2">
                  <Label for="password_confirmation">Подтверждение пароля</Label>
                  <Input id="password_confirmation" v-model="passwordConfirmation" type="password" placeholder="Повторите пароль" required />
                </div>
                <Button type="submit" class="w-full" :disabled="auth.loading">
                  {{ auth.loading ? 'Регистрация...' : 'Зарегистрироваться' }}
                </Button>
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
  </div>
</template>
