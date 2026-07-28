<script setup lang="ts">
defineProps<{
  title: string;
  subtitle: string;
}>();

const emit = defineEmits<{
  submit: [event: Event];
}>();
</script>

<template>
  <div class="auth-form-block">
    <header class="auth-frame__header">
      <h1>{{ title }}</h1>
      <p>{{ subtitle }}</p>
    </header>

    <form class="auth-form" @submit="emit('submit', $event)">
      <slot />
    </form>

    <slot name="footer" />
  </div>
</template>
