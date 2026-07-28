<script setup lang="ts">
import AuthPageLayout from '../components/AuthPageLayout.vue';
import AuthFrame from '../components/AuthFrame.vue';
import ForgotPasswordForm from '../components/ForgotPasswordForm.vue';
</script>

<template>
  <AuthPageLayout tagline="Восстановление доступа займёт всего пару минут.">
    <AuthFrame>
      <ForgotPasswordForm />
    </AuthFrame>
  </AuthPageLayout>
</template>
