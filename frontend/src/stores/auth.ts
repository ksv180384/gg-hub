import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  authApi,
  type RegisterPayload,
  type ResetPasswordPayload,
  type UpdatePasswordPayload,
  type UpdateProfilePayload,
  type User,
  ROLE_ADMIN_SLUG,
} from '@/shared/api/authApi';
import { getErrorMessage } from '@/shared/lib/errorMessage';
import { getActiveRouter } from '@/router/activeRouter';
import { useThemeStore } from '@/stores/theme';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null);
  const loading = ref(false);
  const initialized = ref(false);
  const error = ref<string | null>(null);

  const isAuthenticated = computed(() => !!user.value);

  // isAdmin и права определяются только из ответа api/v1/user (при загрузке страницы и после логина)
  const isAdmin = computed(
    () => !!user.value?.roles?.some((r) => r.slug === ROLE_ADMIN_SLUG)
  );

  function hasPermission(slug: string): boolean {
    if (!user.value) return false;
    if (isAdmin.value) return true;
    return !!user.value.permissions?.includes(slug);
  }

  function hasRole(slug: string): boolean {
    return !!user.value?.roles?.some((r) => r.slug === slug);
  }

  async function fetchUser() {
    loading.value = true;
    error.value = null;
    try {
      const data = await authApi.getUser();
      setUser(data ?? null);
      return user.value;
    } catch {
      setUser(null);
      return null;
    } finally {
      loading.value = false;
    }
  }

  async function login(email: string, password: string, remember = false) {
    loading.value = true;
    error.value = null;
    try {
      const data = await authApi.login(email, password, remember);
      setUser(data.user);
      return data;
    } catch (e: unknown) {
      error.value = getErrorMessage(e, { fields: ['email'], fallback: 'Ошибка входа' });
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function register(payload: RegisterPayload) {
    loading.value = true;
    error.value = null;
    try {
      const data = await authApi.register(payload);
      if (data.requires_email_verification) {
        setUser(null);
        return data;
      }
      setUser(data.user ?? null);
      return data;
    } catch (e: unknown) {
      error.value = getErrorMessage(e, { fields: ['name', 'email'], fallback: 'Ошибка регистрации' });
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function resendVerification(email: string) {
    loading.value = true;
    error.value = null;
    try {
      return await authApi.resendVerification(email);
    } catch (e: unknown) {
      error.value = getErrorMessage(e, { fallback: 'Ошибка отправки' });
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
      setUser(null);
      await getActiveRouter()?.push('/login');
    } catch {
      setUser(null);
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
      error.value = getErrorMessage(e, { fields: ['email'] });
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function resetPassword(payload: ResetPasswordPayload) {
    loading.value = true;
    error.value = null;
    try {
      return await authApi.resetPassword(payload);
    } catch (e: unknown) {
      error.value = getErrorMessage(e, { fields: ['email'] });
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function updatePassword(payload: UpdatePasswordPayload) {
    loading.value = true;
    error.value = null;
    try {
      return await authApi.updatePassword(payload);
    } catch (e: unknown) {
      error.value = getErrorMessage(e, {
        fields: ['current_password', 'password'],
        fallback: 'Ошибка',
      });
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function updateProfile(payload: UpdateProfilePayload) {
    loading.value = true;
    error.value = null;
    try {
      const data = await authApi.updateProfile(payload);
      setUser(data.user);
      return data;
    } catch (e: unknown) {
      error.value = getErrorMessage(e, { fields: ['name', 'timezone', 'theme_preference', 'avatar'], fallback: 'Ошибка сохранения профиля' });
      throw e;
    } finally {
      loading.value = false;
    }
  }

  function setUser(u: User | null) {
    user.value = u;
    initialized.value = true;
    const theme = useThemeStore();
    if (u) {
      theme.setAccountPreference(u.theme_preference ?? 'system');
    } else {
      theme.restoreGuestPreference();
    }
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
    initialized,
    error,
    isAuthenticated,
    isAdmin,
    hasPermission,
    hasRole,
    fetchUser,
    login,
    register,
    resendVerification,
    logout,
    forgotPassword,
    resetPassword,
    updatePassword,
    updateProfile,
    setUser,
    clearError,
    setError,
  };
});
