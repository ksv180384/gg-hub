<script setup>
import {computed, onMounted} from 'vue';
import { Link } from '@inertiajs/vue3';
import { useThemeStore } from '@/stores/themeStore';

import BtnProfileControl from '@/Components/Head/BtnProfileControl.vue';

defineProps({
  // canLogin: { type: Boolean,},
  // canRegister: { type: Boolean },
});

const themeStore = useThemeStore();
const theme = computed(() => themeStore.theme);

const toggleTheme = () => {
  themeStore.toggleTheme();
}

onMounted(() => {
  themeStore.loadTheme();
});
</script>

<template>
  <div class="header">
    <div class="header-container">
      <div class="header-content">
        <div class="header-logo-container">
          <button id="toggleSidebarMobile" aria-expanded="true" aria-controls="sidebar">
            <svg
              id="toggleSidebarMobileHamburger"
              class="w-6 h-6" fill="currentColor"
              viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
            </svg>
            <svg
              id="toggleSidebarMobileClose"
              class="hidden w-6 h-6"
              fill="currentColor"
              viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
          <a
            href="https://flowbite-admin-dashboard.vercel.app/"
            class="logo-container">
            <img
              src="https://flowbite-admin-dashboard.vercel.app/images/logo.svg"
              alt="GG-HUB Logo"
            >
            <span>GG-HUB</span>
          </a>
        </div>
        <div class="header-content-containre">
          <div class="header-nav-containre">
            <ul class="flex flex-col mt-4 font-medium lg:flex-row lg:space-x-8 lg:mt-0">
              <li>
                <Link href="/admin/dashboard" aria-current="page">Dashboard</Link>
              </li>
              <li>
                <Link href="/admin/skills">Skills</Link>
              </li>
            </ul>
          </div>


          <button
            type="button"
            class="theme-toggle-btn"
            @click="toggleTheme"
          >
            <i v-show="theme !== 'dark'" class="pi pi-moon w-5 h-5"></i>
            <i v-show="theme === 'dark'" class="pi pi-sun w-5 h-5"></i>
          </button>

          <BtnProfileControl/>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.header{
  @apply sticky top-0 z-30 w-full bg-white border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700;
}

.header-container{
  @apply px-3 py-3 lg:px-5 lg:pl-3;
}

.header-content{
  @apply flex items-center justify-between;
}

.header-logo-container{
  @apply flex items-center justify-start;
}

.header-logo-container button{
  @apply p-2 text-gray-600 rounded cursor-pointer lg:hidden hover:text-gray-900 hover:bg-gray-100 focus:bg-gray-100
         dark:focus:bg-gray-700 focus:ring-2 focus:ring-gray-100 dark:focus:ring-gray-700 dark:text-gray-400
         dark:hover:bg-gray-700 dark:hover:text-white
}

.logo-container{
  @apply flex ml-2 md:mr-24;
}

.logo-container img{
  @apply h-8 mr-3;
}

.logo-container span{
  @apply self-center text-xl font-semibold sm:text-2xl whitespace-nowrap dark:text-white;
}

.header-content-containre{
  @apply flex items-center;
}

.header-nav-containre{
  @apply hidden justify-between items-center w-full lg:flex lg:w-auto me-2;
}

.header-nav-containre>ul{
  @apply flex flex-col mt-4 font-medium lg:flex-row lg:space-x-8 lg:mt-0;
}

.header-nav-containre>ul>li>a{
  @apply block py-2 pr-4 pl-3 text-white rounded bg-gray-800 lg:bg-transparent lg:text-gray-800 lg:p-0 hover:text-violet-700 dark:text-white;
}




.theme-toggle-btn{
  @apply text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-4
         focus:ring-gray-200 dark:focus:ring-gray-700 rounded-lg text-sm p-2.5;
}
</style>
