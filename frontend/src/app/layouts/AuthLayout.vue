<script setup>

</script>

<template>
  <main class="bg-gray-50 dark:bg-gray-900">
    <div class="flex flex-col items-center justify-center px-6 pt-8 mx-auto md:h-screen pt:mt-0 dark:bg-gray-900">
      <a href="http://localhost:1313/" class="flex items-center justify-center mb-8 text-2xl font-semibold lg:mb-10 dark:text-white">
        <img src="http://localhost:1313/images/logo.svg" class="mr-4 h-11" alt="FlowBite Logo">
        <span>GGHub</span>
      </a>
      <!-- Card -->
      <div class="w-full max-w-xl p-6 space-y-8 sm:p-8 bg-white rounded-lg shadow dark:bg-gray-800">
        <slot/>
      </div>
    </div>

  </main>
</template>

<style scoped>

</style>
