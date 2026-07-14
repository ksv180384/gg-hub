<script setup lang="ts">
import AuthPageLayout from '../components/AuthPageLayout.vue';
import AuthFrame from '../components/AuthFrame.vue';
import ResetPasswordForm from '../components/ResetPasswordForm.vue';
</script>

<template>
  <AuthPageLayout tagline="Создайте новый пароль и возвращайтесь к своей команде.">
    <AuthFrame>
      <ResetPasswordForm />
    </AuthFrame>
  </AuthPageLayout>
</template>
