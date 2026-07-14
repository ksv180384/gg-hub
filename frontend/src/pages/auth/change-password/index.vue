<script setup lang="ts">
import AuthPageLayout from '../components/AuthPageLayout.vue';
import AuthFrame from '../components/AuthFrame.vue';
import ChangePasswordForm from '../components/ChangePasswordForm.vue';
</script>

<template>
  <AuthPageLayout tagline="Защитите аккаунт и продолжайте играть вместе.">
    <AuthFrame>
      <ChangePasswordForm />
    </AuthFrame>
  </AuthPageLayout>
</template>
