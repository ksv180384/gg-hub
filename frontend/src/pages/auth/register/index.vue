<script setup lang="ts">
import { watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AuthPageLayout from '../components/AuthPageLayout.vue';
import AuthFrame from '../components/AuthFrame.vue';
import RegisterForm from '../components/RegisterForm.vue';

const auth = useAuthStore();
const router = useRouter();

watch(
  () => auth.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) router.replace('/');
  },
  { immediate: true },
);
</script>

<template>
  <AuthPageLayout tagline="Создавайте гильдии, находите союзников и покоряйте рейды вместе.">
    <AuthFrame>
      <RegisterForm />
    </AuthFrame>
  </AuthPageLayout>
</template>
