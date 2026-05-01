import { AsyncLocalStorage } from 'node:async_hooks';
import type { SsrRequestContext } from './requestContext.types';

export type { SsrRequestContext };

/** Cookie и Host для исходящих запросов к API при SSR. */
export const ssrRequestContext = new AsyncLocalStorage<SsrRequestContext>();

export function getSsrRequestContext(): SsrRequestContext | undefined {
  return ssrRequestContext.getStore();
}
