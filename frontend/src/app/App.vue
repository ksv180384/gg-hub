<script setup>
import { RouterLink, RouterView } from 'vue-router';
</script>

<template>
  <header>
    app l
    <div class="wrapper">

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
