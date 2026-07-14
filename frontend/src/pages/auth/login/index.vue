<script setup lang="ts">
import { watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AuthPageLayout from '../components/AuthPageLayout.vue';
import AuthFrame from '../components/AuthFrame.vue';
import LoginForm from '../components/LoginForm.vue';

const auth = useAuthStore();
const router = useRouter();
watch(() => auth.isAuthenticated, (value) => { if (value) router.replace('/'); }, { immediate: true });
</script>
<template>
  <AuthPageLayout>
    <AuthFrame><LoginForm /></AuthFrame>
  </AuthPageLayout>
</template>
