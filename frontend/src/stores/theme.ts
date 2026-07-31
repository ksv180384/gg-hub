import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
  isThemePreference,
  type ThemePreference,
} from '@/shared/lib/themePreference';

export type { ThemePreference } from '@/shared/lib/themePreference';

const STORAGE_KEY = 'gg-theme';
const PREPAINT_STORAGE_KEY = 'gg-theme-prepaint';
const root = typeof document !== 'undefined' ? document.documentElement : null;

function getSystemDark(): boolean {
  if (typeof window === 'undefined' || !window.matchMedia) return false;
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function applyTheme(isDark: boolean) {
  if (!root) return;
  root.classList.toggle('dark', isDark);
  root.style.colorScheme = isDark ? 'dark' : 'light';
}

function loadStored(): ThemePreference {
  if (typeof localStorage === 'undefined') return 'system';
  const value = localStorage.getItem(STORAGE_KEY);
  return isThemePreference(value) ? value : 'system';
}

function loadPrepaintStored(): ThemePreference | null {
  if (typeof localStorage === 'undefined') return null;
  const value = localStorage.getItem(PREPAINT_STORAGE_KEY);
  return isThemePreference(value) ? value : null;
}

function cachePrepaintPreference(value: ThemePreference) {
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(PREPAINT_STORAGE_KEY, value);
  }
  if (typeof document === 'undefined' || typeof window === 'undefined') return;

  const hostname = window.location.hostname;
  const sharedDomain = ['gg-hub.ru', 'gg-hub.local']
    .find((domain) => hostname === domain || hostname.endsWith(`.${domain}`));
  const cookieParts = [
    `${PREPAINT_STORAGE_KEY}=${value}`,
    'Path=/',
    'Max-Age=31536000',
    'SameSite=Lax',
  ];
  if (sharedDomain) {
    cookieParts.push(`Domain=.${sharedDomain}`);
  }
  document.cookie = cookieParts.join('; ');
}

export const useThemeStore = defineStore('theme', () => {
  const preference = ref<ThemePreference>('system');
  const isDark = ref(getSystemDark());
  let systemListenerRegistered = false;

  function updateEffective() {
    isDark.value = preference.value === 'system'
      ? getSystemDark()
      : preference.value === 'dark';
    cachePrepaintPreference(preference.value);
    applyTheme(isDark.value);
  }

  function setPreference(value: ThemePreference) {
    preference.value = value;
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, value);
    }
    updateEffective();
  }

  function setAccountPreference(value: ThemePreference) {
    preference.value = value;
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, value);
    }
    updateEffective();
  }

  function restoreGuestPreference() {
    preference.value = loadStored();
    updateEffective();
  }

  function init(accountPreference?: ThemePreference) {
    if (accountPreference) {
      preference.value = accountPreference;
    } else {
      preference.value = loadPrepaintStored() ?? loadStored();
    }
    updateEffective();

    if (typeof window !== 'undefined' && window.matchMedia && !systemListenerRegistered) {
      systemListenerRegistered = true;
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (preference.value === 'system') {
          updateEffective();
        }
      });
    }
  }

  return {
    preference,
    isDark,
    setPreference,
    setAccountPreference,
    restoreGuestPreference,
    init,
    updateEffective,
  };
});
