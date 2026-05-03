import { getActiveRouter } from '@/router/activeRouter';
import { http } from '@/shared/api/http';
import { attachHttpResponseInterceptor } from '@/shared/api/http-interceptors-core';
import { getSsrRequestContext } from '../../ssr/requestContext';

/** SSR: Cookie и Host из AsyncLocalStorage (см. entry-server). */
export function setupSsrHttpInterceptors(): void {
  const axiosInstance = http.instance;

  axiosInstance.interceptors.request.use(
    (config) => {
      const ctx = getSsrRequestContext();
      if (ctx?.cookie) {
        config.headers.set('Cookie', ctx.cookie);
      }
      if (ctx?.host) {
        config.headers.set('Host', ctx.host);
        config.headers.set('X-Site-Host', ctx.host);
      }
      return config;
    },
    (error) => Promise.reject(error),
  );

  attachHttpResponseInterceptor(axiosInstance, getActiveRouter);
}
