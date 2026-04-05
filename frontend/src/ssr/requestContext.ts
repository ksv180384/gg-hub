import { AsyncLocalStorage } from 'node:async_hooks';

export interface SsrRequestContext {
  cookie?: string;
  host?: string;
}

/** Cookie и Host для исходящих запросов к API при SSR. */
export const ssrRequestContext = new AsyncLocalStorage<SsrRequestContext>();

export function getSsrRequestContext(): SsrRequestContext | undefined {
  return ssrRequestContext.getStore();
}
