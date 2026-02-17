<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { Button, Input, Label, Card, CardContent } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();
const email = ref('');
const sent = ref(false);

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
  sent.value = false;
  try {
    await auth.forgotPassword(email.value);
    sent.value = true;
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
            <h1 class="text-2xl font-bold">Восстановление пароля</h1>
            <p class="text-sm text-muted-foreground">
              Введите email — мы отправим ссылку для сброса пароля
            </p>
          </div>
          <Card v-if="!sent">
            <CardContent class="pt-6">
              <form class="flex flex-col gap-4" @submit="onSubmit">
                <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>
                <div class="space-y-2">
                  <Label for="email">Email</Label>
                  <Input id="email" v-model="email" type="email" placeholder="you@example.com" required />
                </div>
                <Button type="submit" class="w-full" :disabled="auth.loading">
                  {{ auth.loading ? 'Отправка...' : 'Отправить ссылку' }}
                </Button>
              </form>
            </CardContent>
          </Card>
          <Card v-else>
            <CardContent class="pt-6 text-center">
              <p class="text-sm text-muted-foreground">
                Если аккаунт с таким email существует, мы отправили на него ссылку для сброса пароля.
              </p>
              <RouterLink to="/login">
                <Button variant="outline" class="mt-4 w-full">Вернуться к входу</Button>
              </RouterLink>
            </CardContent>
          </Card>
          <p class="text-center text-sm text-muted-foreground">
            <RouterLink to="/login" class="font-medium text-primary underline-offset-4 hover:underline">
              ← Назад к входу
            </RouterLink>
          </p>
        </div>
      </div>
    </div>
    <div class="relative hidden bg-muted lg:block">
      <div class="absolute inset-0 bg-gradient-to-br from-primary/20 via-background to-primary/5" />
      <div class="absolute inset-0 flex items-center justify-center p-12">
        <p class="max-w-md text-center text-lg text-muted-foreground">
          Забыли пароль? Не проблема — восстановление займёт пару минут.
        </p>
      </div>
    </div>
  </div>
</template>
