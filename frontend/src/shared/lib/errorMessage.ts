/**
 * Форма ошибки от API (Laravel-style: errors по полям + общее message).
 */
export interface ApiErrorShape {
  errors?: Record<string, string[]>;
  message?: string;
}

export interface GetErrorMessageOptions {
  /** Проверить поля по порядку и вернуть первое непустое сообщение. */
  fields?: string[];
  /** Сообщение по умолчанию, если ничего не найдено. */
  fallback?: string;
}

const DEFAULT_FALLBACK = 'Ошибка';

/** Сообщения axios/сети, которые не показываем пользователю. */
const GENERIC_HTTP_MESSAGE = /^(?:Request failed with status code \d+|Network Error|timeout of \d+ms exceeded)$/i;

/**
 * Извлекает читаемое сообщение из ошибки API (catch (e: unknown)).
 * Поддерживает errors[field][0] и message.
 *
 * @param e — объект из catch
 * @param options.fields — приоритетные поля (например ['email'], ['current_password', 'password'])
 * @param options.fallback — строка по умолчанию
 */
export function getErrorMessage(
  e: unknown,
  options: GetErrorMessageOptions = {}
): string {
  const { fields, fallback = DEFAULT_FALLBACK } = options;
  const err = e as ApiErrorShape;

  if (fields?.length) {
    for (const field of fields) {
      const value = err.errors?.[field]?.[0];
      if (value?.trim()) return value;
    }
  } else if (err.errors) {
    const first = Object.values(err.errors).flat()[0];
    if (first?.trim()) return first;
  }

  const message = err.message?.trim();
  if (message && !GENERIC_HTTP_MESSAGE.test(message)) {
    return message;
  }
  return fallback;
}
