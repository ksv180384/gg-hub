import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

interface TestingResponse {
  message: string;
}

export const adminTestingApi = {
  async sendTelegram(message: string): Promise<string> {
    const response = await http.fetchPost<TestingResponse>('/testing/telegram', {
      message,
    });
    throwOnError(response, 'Не удалось отправить сообщение в Telegram');
    return response.data?.message ?? 'Сообщение отправлено в Telegram.';
  },

  async sendEmail(email: string, message: string): Promise<string> {
    const response = await http.fetchPost<TestingResponse>('/testing/email', {
      email,
      message,
    });
    throwOnError(response, 'Не удалось отправить письмо');
    return response.data?.message ?? 'Письмо отправлено.';
  },
};
