import axios from 'axios';
import type { AxiosInstance } from 'axios';
import type { AxiosRequestConfig } from 'axios';

interface HttpConfig {
  baseUrl: string;
  defaultHeaders: Record<string, string>;
}

export interface HttpResponse<T> {
  data: T | null;
  status: number;
}

interface HttpClient {
  instance: AxiosInstance;
  fetchData: <T>(config: AxiosRequestConfig) => Promise<T | null>;
  isSuccess: (config: AxiosRequestConfig) => Promise<boolean>;
  fetchFull: <T>(config: AxiosRequestConfig) => Promise<HttpResponse<T>>;
  fetchPut: <T>(path: string, data?: FormData | Record<string, unknown>, config?: AxiosRequestConfig) => Promise<HttpResponse<T>>;
  fetchPost: <T>(path: string, data?: FormData | Record<string, unknown>, config?: AxiosRequestConfig) => Promise<HttpResponse<T>>;
  fetchGet: <T>(path: string, config?: AxiosRequestConfig) => Promise<HttpResponse<T>>;
  fetchDelete: <T>(path: string, config?: AxiosRequestConfig) => Promise<HttpResponse<T>>;
  fetchDeleteWithBody: <T>(path: string, data?: unknown, config?: AxiosRequestConfig) => Promise<HttpResponse<T>>;
  getCsrfToken: () => Promise<void>;
  isCsrfInitialized: () => boolean;
}

const httpClient = ({ baseUrl, defaultHeaders }: HttpConfig): HttpClient => {
  const axiosInstant = axios.create({
    baseURL: baseUrl,
    headers: defaultHeaders,
    withCredentials: true,
  });

  let csrfInitialized = false;

  const getCsrfToken = async (): Promise<void> => {
    try {
      await axiosInstant.get('/sanctum/csrf-cookie');
      csrfInitialized = true;
    } catch (error) {
      console.error('Ошибка получения CSRF token:', error);
      throw error;
    }
  };

  const request = async <T>(config: AxiosRequestConfig): Promise<HttpResponse<T>> => {
    const headers = { ...config.headers } as NonNullable<typeof config.headers>;

    try {
      const { data, status } = await axiosInstant.request<T>({
        ...config,
        headers,
      });
      return { data, status };
    } catch (err: unknown) {
      // Ошибка с response (стандартное поведение axios)
      if (axios.isAxiosError(err) && err.response) {
        const status = err.response.status ?? 500;
        const responseData = err.response.data;
        if (status === 419 && csrfInitialized) {
          await getCsrfToken();
          return request<T>(config);
        }
        if (status === 422) {
          const data =
            responseData != null && typeof responseData === 'object' && ('message' in responseData || 'errors' in responseData)
              ? (responseData as T)
              : ({ message: typeof err.message === 'string' ? err.message : 'Ошибка валидации' } as T);
          return { data, status };
        }
        return {
          data: (responseData ?? null) as T | null,
          status,
        };
      }
      // Ошибка с полями status и message напрямую (без err.response)
      const errObj = err as { status?: number; message?: string; data?: unknown };
      if (errObj && typeof errObj === 'object' && typeof errObj.status === 'number') {
        const status = errObj.status;
        const data =
          errObj.data != null && typeof errObj.data === 'object' && ('message' in errObj.data || 'errors' in errObj.data)
            ? (errObj.data as T)
            : ({ message: typeof errObj.message === 'string' ? errObj.message : 'Ошибка' } as T);
        return { data, status };
      }
      return { data: null, status: 500 };
    }
  };

  const fetchData = async <T>(config: AxiosRequestConfig): Promise<T | null> => {
    const { data } = await request<T>(config);
    return data;
  };

  const isSuccess = async (config: AxiosRequestConfig): Promise<boolean> => {
    const { status } = await request(config);
    return status >= 200 && status < 300;
  };

  const fetchFull = async <T>(config: AxiosRequestConfig): Promise<HttpResponse<T>> => {
    return request<T>(config);
  };

  const fetchGet = async <T>(path: string, config: AxiosRequestConfig = {}): Promise<HttpResponse<T>> => {
    return request<T>({
      ...config,
      url: path,
      method: 'GET',
      headers: {
        ...defaultHeaders,
        ...config.headers,
      },
    });
  };

  const fetchPost = async <T>(
    path: string,
    data?: FormData | Record<string, unknown>,
    config: AxiosRequestConfig = {}
  ): Promise<HttpResponse<T>> => {
    const isFormData = data instanceof FormData;
    const headers = {
      ...config.headers,
      ...(isFormData
        ? { 'Content-Type': false }
        : { 'Content-Type': 'application/json' }),
    };
    return request<T>({
      ...config,
      url: path,
      method: 'POST',
      headers,
      data: isFormData ? data : data != null ? JSON.stringify(data) : undefined,
    });
  };

  const fetchPut = async <T>(
    path: string,
    data?: FormData | Record<string, unknown>,
    config: AxiosRequestConfig = {}
  ): Promise<HttpResponse<T>> => {
    const isFormData = data instanceof FormData;
    const headers = {
      ...config.headers,
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
    };
    return request<T>({
      ...config,
      url: path,
      method: 'PUT',
      headers,
      data: isFormData ? data : data != null ? JSON.stringify(data) : undefined,
    });
  };

  const fetchDelete = async <T>(path: string, config: AxiosRequestConfig = {}): Promise<HttpResponse<T>> => {
    return request<T>({
      ...config,
      url: path,
      method: 'DELETE',
      headers: {
        ...defaultHeaders,
        ...config.headers,
      },
    });
  };

  const fetchDeleteWithBody = async <T>(
    path: string,
    data?: unknown,
    config: AxiosRequestConfig = {}
  ): Promise<HttpResponse<T>> => {
    return request<T>({
      ...config,
      url: path,
      method: 'DELETE',
      headers: {
        ...defaultHeaders,
        ...config.headers,
      },
      ...(data !== undefined && data !== null && { data }),
    });
  };

  const isCsrfInitialized = (): boolean => csrfInitialized;

  return {
    instance: axiosInstant,
    fetchData,
    isSuccess,
    fetchFull,
    fetchGet,
    fetchPost,
    fetchPut,
    fetchDelete,
    fetchDeleteWithBody,
    getCsrfToken,
    isCsrfInitialized,
  };
};

const rootUrl = (import.meta.env.VITE_API_URL ?? '/').replace(/\/$/, '') || '';
const apiBaseUrl = rootUrl + '/api/v1';
const defaultHeaders = {
  'Content-Type': 'application/json',
  Accept: 'application/json',
} as const;

const http = httpClient({ baseUrl: apiBaseUrl, defaultHeaders });

export { http };
