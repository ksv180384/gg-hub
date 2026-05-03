import type { AxiosInstance } from 'axios';
import { getActivePinia } from 'pinia';
import type { Router } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { http } from '@/shared/api/http';

/**
 * Общая логика ответов axios (419, 401). Роутер передаётся снаружи — на SSR редирект не выполняется.
 */
export function attachHttpResponseInterceptor(
  axiosInstance: AxiosInstance,
  getRouter: () => Router | null,
): void {
  axiosInstance.interceptors.response.use(
    (response) => response,
    async (error) => {
      const { http } = await import('@/shared/api/http');
      if (error.response?.status === 419) {
        if (import.meta.env.SSR) {
          return Promise.reject(error);
        }
        await http.getCsrfToken();
        return axiosInstance(error.config);
      }
      if (error.response?.status === 401) {
        const pinia = getActivePinia();
        if (pinia) {
          const authStore = useAuthStore(pinia);
          authStore.setUser(null);
        }
        if (!import.meta.env.SSR) {
          const router = getRouter();
          const currentRoute = router?.currentRoute.value;
          const requiresAuth = currentRoute?.meta?.requiresAuth === true;
          if (requiresAuth && router) {
            await router.push({ name: 'login' });
          }
        }
      }
      const data = error.response?.data;
      const err = new Error(
        (data as { message?: string })?.message || error.message,
      ) as Error & { status?: number; errors?: Record<string, string[]> };
      err.status = error.response?.status;
      err.errors = (data as { errors?: Record<string, string[]> })?.errors;
      return Promise.reject(err);
    },
  );
}
