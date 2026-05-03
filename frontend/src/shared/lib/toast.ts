import { reactive } from 'vue';

export const toastState = reactive({
  message: null as string | null,
});

let hideTimer: ReturnType<typeof setTimeout> | null = null;

/** Сообщение об ошибке (например, когда нет места под текст в выпадающем блоке). */
export function toastError(message: string): void {
  if (hideTimer != null) {
    clearTimeout(hideTimer);
    hideTimer = null;
  }
  toastState.message = message;
  hideTimer = setTimeout(() => {
    toastState.message = null;
    hideTimer = null;
  }, 6500);
}
