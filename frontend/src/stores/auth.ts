import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authApi, type User } from '@/shared/api/authApi';
import router from '@/router';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const isAuthenticated = computed(() => !!user.value);

  async function fetchUser() {
    loading.value = true;
    error.value = null;
    try {
      const data = await authApi.getUser();
      user.value = data ?? null;
      return user.value;
    } catch {
      user.value = null;
      return null;
    } finally {
      loading.value = false;
    }
  }

  async function login(email: string, password: string) {
    loading.value = true;
    error.value = null;
    try {
      const data = await authApi.login(email, password);
      user.value = data.user;
      return data;
    } catch (e: unknown) {
      const err = e as { errors?: Record<string, string[]>; message?: string };
      error.value = err.errors?.email?.[0] ?? err.message ?? 'Ошибка входа';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function register(payload: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) {
    loading.value = true;
    error.value = null;
    try {
      const data = await authApi.register(payload);
      user.value = data.user;
      return data;
    } catch (e: unknown) {
      const err = e as { errors?: Record<string, string[]>; message?: string };
      const firstError = err.errors ? Object.values(err.errors).flat()[0] : err.message;
      error.value = firstError ?? 'Ошибка регистрации';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    loading.value = true;
    error.value = null;
    try {
      await authApi.logout();
      user.value = null;
      await router.push('/login');
    } catch {
      user.value = null;
    } finally {
      loading.value = false;
    }
  }

  async function forgotPassword(email: string) {
    loading.value = true;
    error.value = null;
    try {
      return await authApi.forgotPassword(email);
    } catch (e: unknown) {
      const err = e as { errors?: Record<string, string[]>; message?: string };
      error.value = err.errors?.email?.[0] ?? err.message ?? 'Ошибка';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function resetPassword(payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) {
    loading.value = true;
    error.value = null;
    try {
      return await authApi.resetPassword(payload);
    } catch (e: unknown) {
      const err = e as { errors?: Record<string, string[]>; message?: string };
      error.value = err.errors?.email?.[0] ?? err.message ?? 'Ошибка';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function updatePassword(payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }) {
    loading.value = true;
    error.value = null;
    try {
      return await authApi.updatePassword(payload);
    } catch (e: unknown) {
      const err = e as { errors?: Record<string, string[]>; message?: string };
      error.value =
        err.errors?.current_password?.[0] ?? err.errors?.password?.[0] ?? err.message ?? 'Ошибка';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  function setUser(u: User | null) {
    user.value = u;
  }

  function clearError() {
    error.value = null;
  }

  function setError(msg: string | null) {
    error.value = msg;
  }

  return {
    user,
    loading,
    error,
    isAuthenticated,
    fetchUser,
    login,
    register,
    logout,
    forgotPassword,
    resetPassword,
    updatePassword,
    setUser,
    clearError,
    setError,
  };
});
