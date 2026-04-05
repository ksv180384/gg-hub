import { getActiveRouter } from '@/router/activeRouter';
import { http } from '@/shared/api/http';
import { attachHttpResponseInterceptor } from '@/shared/api/http-interceptors-core';

/** Клиент: X-Site-Host из window, ответы через общий перехватчик. */
export function setupHttpInterceptors(): void {
  const axiosInstance = http.instance;

  axiosInstance.interceptors.request.use(
    (config) => {
      if (typeof window !== 'undefined' && window.location?.host) {
        config.headers.set('X-Site-Host', window.location.host);
      }
      return config;
    },
    (error) => Promise.reject(error),
  );

  attachHttpResponseInterceptor(axiosInstance, getActiveRouter);
}
