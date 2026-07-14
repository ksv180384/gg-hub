<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Button, Input, Label } from '@/shared/ui';
import { useAuthStore } from '@/stores/auth';
import AuthFormBlock from './AuthFormBlock.vue';

const auth = useAuthStore();
const email = ref('');
const sent = ref(false);

onMounted(() => auth.clearError());

async function submit(event: Event) {
  event.preventDefault();
  sent.value = false;
  try {
    await auth.forgotPassword(email.value);
    sent.value = true;
  } catch {
    // Ошибка хранится в auth.error.
  }
}
</script>

<template>
  <AuthFormBlock
    title="Восстановление пароля"
    subtitle="Отправим ссылку для сброса пароля"
    @submit="submit"
  >
    <template v-if="!sent">
      <p v-if="auth.error" class="text-sm text-destructive">{{ auth.error }}</p>
      <div class="auth-field">
        <Label for="email">Email</Label>
        <Input id="email" v-model="email" type="email" placeholder="Email" required />
      </div>
      <Button type="submit" class="auth-submit w-full" :disabled="auth.loading">
        {{ auth.loading ? 'Отправка...' : 'Отправить ссылку' }}
      </Button>
    </template>
    <p v-else class="text-center text-sm text-white/70">
      Если аккаунт существует, ссылка для сброса уже отправлена.
    </p>
    <template #footer>
      <p class="mt-6 text-center text-sm"><RouterLink to="/login">← Назад ко входу</RouterLink></p>
    </template>
  </AuthFormBlock>
</template>
