<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Button, Input, Label, Card, CardContent } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const currentPassword = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const success = ref(false);

onMounted(() => {
  auth.clearError();
  if (!auth.isAuthenticated) {
    router.replace('/login');
  }
});

async function onSubmit(e: Event) {
  e.preventDefault();
  if (password.value !== passwordConfirmation.value) {
    auth.error = 'Пароли не совпадают';
    return;
  }
  try {
    await auth.updatePassword({
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    success.value = true;
  } catch {
    // ошибка в auth.error
  }
}
</script>

<template>
  <div class="container max-w-md py-12">
    <h1 class="mb-6 text-2xl font-bold">Смена пароля</h1>
    <Card v-if="!success">
      <CardContent class="pt-6">
        <form class="flex flex-col gap-4" @submit="onSubmit">
          <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>
          <div class="space-y-2">
            <Label for="current_password">Текущий пароль</Label>
            <Input id="current_password" v-model="currentPassword" type="password" required />
          </div>
          <div class="space-y-2">
            <Label for="password">Новый пароль</Label>
            <Input id="password" v-model="password" type="password" required />
          </div>
          <div class="space-y-2">
            <Label for="password_confirmation">Подтверждение нового пароля</Label>
            <Input id="password_confirmation" v-model="passwordConfirmation" type="password" required />
          </div>
          <Button type="submit" class="w-full" :disabled="auth.loading">
            {{ auth.loading ? 'Сохранение...' : 'Сохранить пароль' }}
          </Button>
        </form>
      </CardContent>
    </Card>
    <Card v-else>
      <CardContent class="pt-6 text-center">
        <p class="text-sm text-muted-foreground">Пароль успешно изменён.</p>
        <Button class="mt-4" @click="router.push('/')">На главную</Button>
      </CardContent>
    </Card>
  </div>
</template>
