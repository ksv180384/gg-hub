import { getActivePinia } from 'pinia';
import router from '@/router';
import { useAuthStore } from '@/stores/auth';
import { http } from '@/shared/api/http';

export const setupHttpInterceptors = (): void => {
  const axiosInstance = http.instance;

  axiosInstance.interceptors.request.use(
    (config) => {
      if (typeof window !== 'undefined' && window.location?.host) {
        config.headers.set('X-Site-Host', window.location.host);
      }
      return config;
    },
    (error) => Promise.reject(error)
  );

  axiosInstance.interceptors.response.use(
    (response) => response,
    async (error) => {
      if (error.response?.status === 419) {
        await http.getCsrfToken();
        return axiosInstance(error.config);
      }
      if (error.response?.status === 401) {
        const pinia = getActivePinia();
        if (pinia) {
          const authStore = useAuthStore(pinia);
          authStore.setUser(null);
        }
        const currentRoute = router.currentRoute.value;
        const requiresAuth = currentRoute.meta?.requiresAuth === true;
        if (requiresAuth) {
          await router.push({ name: 'login' });
        }
      }
      const data = error.response?.data;
      const err = new Error(
        (data as { message?: string })?.message || error.message
      ) as Error & { status?: number; errors?: Record<string, string[]> };
      err.status = error.response?.status;
      err.errors = (data as { errors?: Record<string, string[]> })?.errors;
      return Promise.reject(err);
    }
  );
};
