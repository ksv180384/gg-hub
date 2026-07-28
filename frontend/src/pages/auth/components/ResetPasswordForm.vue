<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { Button, Input, Label } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import AuthFormBlock from './AuthFormBlock.vue';

const auth = useAuthStore();
const route = useRoute();
const token = computed(() => (route.query.token as string) ?? '');
const email = ref((route.query.email as string) ?? '');
const password = ref('');
const confirmation = ref('');
const success = ref(false);

onMounted(() => auth.clearError());

async function submit(event: Event) {
  event.preventDefault();
  if (password.value !== confirmation.value) {
    auth.setError('Пароли не совпадают');
    return;
  }
  if (!token.value) {
    auth.setError('Отсутствует токен сброса.');
    return;
  }
  try {
    await auth.resetPassword({
      token: token.value,
      email: email.value,
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
  <AuthFormBlock title="Новый пароль" subtitle="Задайте новый пароль для аккаунта" @submit="submit">
    <template v-if="!success">
      <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>
      <div class="auth-field">
        <Label for="reset-email">Email</Label>
        <Input id="reset-email" v-model="email" type="email" placeholder="Email" required />
      </div>
      <div class="auth-field">
        <Label for="reset-password">Новый пароль</Label>
        <Input id="reset-password" v-model="password" type="password" placeholder="Новый пароль" required />
      </div>
      <div class="auth-field">
        <Label for="reset-confirmation">Повторите пароль</Label>
        <Input id="reset-confirmation" v-model="confirmation" type="password" placeholder="Повторите пароль" required />
      </div>
      <Button type="submit" class="auth-submit w-full" :disabled="auth.loading">
        {{ auth.loading ? 'Сохранение...' : 'Сохранить пароль' }}
      </Button>
    </template>
    <p v-else class="text-center text-white/70">Пароль успешно изменён.</p>
    <template #footer>
      <p class="mt-6 text-center text-sm"><RouterLink to="/login">Перейти ко входу</RouterLink></p>
    </template>
  </AuthFormBlock>
</template>
