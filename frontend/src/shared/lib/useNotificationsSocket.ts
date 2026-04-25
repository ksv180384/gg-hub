/**
 * Socket.IO-подписка на персональный канал оповещений пользователя.
 *
 * Поведение подключения аналогично `useGuildAuctionWheelSocket`:
 * - Без `VITE_SOCKET_URL` (или пусто) — same-origin через `/socket.io` (проксирует nginx/vite).
 * - `VITE_SOCKET_URL=off` — не подключаться.
 *
 * Сервер — источник истины (backend API). Socket отправляет лишь события о изменениях,
 * клиент применяет их к локальному состоянию списка/счётчика непрочитанных.
 */

import { io, type Socket } from 'socket.io-client';
import {
  onMounted,
  onUnmounted,
  shallowRef,
  watch,
  type Ref,
} from 'vue';
import type { NotificationItem } from '@/shared/api/notificationsApi';

export interface NotificationCreatedEvent {
  userId: number;
  notification: NotificationItem;
  unreadCount: number;
}

export interface NotificationDeletedEvent {
  userId: number;
  ids: number[];
  unreadCount: number;
}

export interface NotificationReadEvent {
  userId: number;
  id: number;
  readAt: string;
  unreadCount: number;
}

export interface UseNotificationsSocketOptions {
  /** Id текущего пользователя. 0 или null — отключено. */
  userId: Ref<number | null>;
  onCreated?: (event: NotificationCreatedEvent) => void;
  onDeleted?: (event: NotificationDeletedEvent) => void;
  onRead?: (event: NotificationReadEvent) => void;
}

function readSocketUrl(): { configured: boolean; url: string | undefined } {
  const rawEnv = (import.meta.env.VITE_SOCKET_URL as string | undefined)?.trim() ?? '';
  const syncOff = rawEnv === 'off' || rawEnv === 'false';
  if (syncOff) return { configured: false, url: undefined };
  return { configured: true, url: rawEnv.length > 0 ? rawEnv : undefined };
}

export function useNotificationsSocket(options: UseNotificationsSocketOptions) {
  const socketRef = shallowRef<Socket | null>(null);
  const { configured, url } = readSocketUrl();

  function join(userId: number) {
    const s = socketRef.value;
    if (!s?.connected || userId <= 0) return;
    s.emit('notifications:join', { userId });
  }

  function leave(userId: number) {
    const s = socketRef.value;
    if (!s?.connected || userId <= 0) return;
    s.emit('notifications:leave', { userId });
  }

  onMounted(() => {
    if (!configured || import.meta.env.SSR) return;

    const s = io(url, {
      transports: ['websocket', 'polling'],
      path: '/socket.io',
      autoConnect: true,
    });
    socketRef.value = s;

    s.on('connect', () => {
      const id = options.userId.value ?? 0;
      if (id > 0) join(id);
    });

    s.on('notification:created', (event: NotificationCreatedEvent) => {
      if (!event || event.userId !== options.userId.value) return;
      options.onCreated?.(event);
    });

    s.on('notification:deleted', (event: NotificationDeletedEvent) => {
      if (!event || event.userId !== options.userId.value) return;
      options.onDeleted?.(event);
    });

    s.on('notification:read', (event: NotificationReadEvent) => {
      if (!event || event.userId !== options.userId.value) return;
      options.onRead?.(event);
    });
  });

  onUnmounted(() => {
    const id = options.userId.value ?? 0;
    if (id > 0) leave(id);
    socketRef.value?.disconnect();
    socketRef.value = null;
  });

  watch(
    () => options.userId.value,
    (id, prevId) => {
      if (prevId && prevId > 0) leave(prevId);
      if (id && id > 0) join(id);
    }
  );

  return {
    socketConfigured: configured,
  };
}
