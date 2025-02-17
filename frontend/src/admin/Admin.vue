<script setup>
import { RouterLink, RouterView } from 'vue-router';
</script>

<template>
  <header>

    <div class="wrapper">
      admin
      <nav>
        <router-link to="/">Home</router-link>
        <router-link :to="{ name: 'login' }">Login</router-link>
      </nav>
    </div>
  </header>

  <router-view />
</template>

<style scoped>

</style>
