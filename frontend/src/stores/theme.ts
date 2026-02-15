import { defineStore } from 'pinia';
import { ref } from 'vue';

export type ThemePreference = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'gg-theme';
const root = typeof document !== 'undefined' ? document.documentElement : null;

function getSystemDark(): boolean {
  if (typeof window === 'undefined' || !window.matchMedia) return false;
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function applyTheme(isDark: boolean) {
  if (!root) return;
  if (isDark) {
    root.classList.add('dark');
  } else {
    root.classList.remove('dark');
  }
}

function loadStored(): ThemePreference {
  if (typeof localStorage === 'undefined') return 'system';
  const v = localStorage.getItem(STORAGE_KEY);
  if (v === 'light' || v === 'dark' || v === 'system') return v;
  return 'system';
}

export const useThemeStore = defineStore('theme', () => {
  const preference = ref<ThemePreference>(loadStored());

  const isDark = ref(getSystemDark());

  function setPreference(value: ThemePreference) {
    preference.value = value;
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, value);
    }
    updateEffective();
  }

  function updateEffective() {
    if (preference.value === 'system') {
      isDark.value = getSystemDark();
    } else {
      isDark.value = preference.value === 'dark';
    }
    applyTheme(isDark.value);
  }

  function init() {
    updateEffective();
    if (typeof window !== 'undefined' && window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (preference.value === 'system') {
          isDark.value = getSystemDark();
          applyTheme(isDark.value);
        }
      });
    }
  }

  return {
    preference,
    isDark,
    setPreference,
    init,
    updateEffective,
  };
});
