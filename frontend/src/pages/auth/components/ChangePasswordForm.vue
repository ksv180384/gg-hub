<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Button, Input, Label } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import AuthFormBlock from './AuthFormBlock.vue';

const auth = useAuthStore();
const router = useRouter();
const current = ref('');
const password = ref('');
const confirmation = ref('');
const success = ref(false);

onMounted(() => {
  auth.clearError();
  if (!auth.isAuthenticated) router.replace('/login');
});

async function submit(event: Event) {
  event.preventDefault();
  if (password.value !== confirmation.value) {
    auth.setError('Пароли не совпадают');
    return;
  }
  try {
    await auth.updatePassword({
      current_password: current.value,
      password: password.value,
      password_confirmation: confirmation.value,
    });
    success.value = true;
  } catch {
    // Ошибка хранится в auth.error.
  }
}
</script>

<template>
  <AuthFormBlock title="Смена пароля" subtitle="Обновите пароль своего аккаунта" @submit="submit">
    <template v-if="!success">
      <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>
      <div class="auth-field">
        <Label for="current-password">Текущий пароль</Label>
        <Input id="current-password" v-model="current" type="password" placeholder="Текущий пароль" required />
      </div>
      <div class="auth-field">
        <Label for="new-password">Новый пароль</Label>
        <Input id="new-password" v-model="password" type="password" placeholder="Новый пароль" required />
      </div>
      <div class="auth-field">
        <Label for="new-password-confirmation">Повторите пароль</Label>
        <Input id="new-password-confirmation" v-model="confirmation" type="password" placeholder="Повторите пароль" required />
      </div>
      <Button type="submit" class="auth-submit w-full" :disabled="auth.loading">
        {{ auth.loading ? 'Сохранение...' : 'Сохранить пароль' }}
      </Button>
    </template>
    <p v-else class="text-center text-white/70">Пароль успешно изменён.</p>
    <template #footer>
      <p class="mt-6 text-center text-sm">
        <button type="button" class="text-amber-400" @click="router.push('/')">На главную</button>
      </p>
    </template>
  </AuthFormBlock>
</template>
