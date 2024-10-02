import { defineStore } from 'pinia';

export const useThemeStore = defineStore('theme', {
  state: () => ({
    theme: 'light',
  }),
  actions: {
    toggleTheme() {
      this.theme = localStorage.getItem('theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('theme', this.theme);
      document.documentElement.classList.toggle('dark');
    },
    loadTheme() {
      this.theme = localStorage.getItem('theme');
    },
  },
});
