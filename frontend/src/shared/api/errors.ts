/**
 * Единая обработка ошибок API: извлечение сообщения из ответа (Laravel: message, errors)
 * и выброс Error с полями status и errors.
 */

import type { HttpResponse } from '@/shared/api/http';

/** Извлекает текст ошибки из тела ответа (объект { message?, errors? } или JSON-строка). */
export function getErrorMessage(body: unknown, fallback: string): string {
  if (body == null) return fallback;
  let obj: { message?: string; errors?: Record<string, string[] | string> } | null = null;
  if (typeof body === 'object' && body !== null && 'message' in body) {
    obj = body as { message?: string; errors?: Record<string, string[] | string> };
  } else if (typeof body === 'string') {
    try {
      obj = JSON.parse(body) as { message?: string; errors?: Record<string, string[] | string> };
    } catch {
      return fallback;
    }
  }
  if (!obj || typeof obj !== 'object') return fallback;
  if (obj.errors && typeof obj.errors === 'object') {
    for (const v of Object.values(obj.errors)) {
      const msg = Array.isArray(v) ? v[0] : v;
      if (typeof msg === 'string' && msg) return msg;
    }
  }
  if (typeof obj.message === 'string' && obj.message) return obj.message;
  return fallback;
}

export interface ApiError extends Error {
  status?: number;
  errors?: Record<string, string[]>;
}

/**
 * При status >= 400 выбрасывает Error с сообщением из ответа (errors[0] или message) и fallback.
 * После вызова без исключения тип res сужается для успешного ответа.
 */
export function throwOnError<T>(
  res: HttpResponse<T>,
  fallbackMessage: string
): asserts res is HttpResponse<T> & { data: T } {
  if (res.status >= 400) {
    const err = new Error(getErrorMessage(res.data, fallbackMessage)) as ApiError;
    err.status = res.status;
    const body = res.data as { errors?: Record<string, string[]> } | null;
    if (body && typeof body === 'object' && body.errors) err.errors = body.errors;
    throw err;
  }
}
