import type { Socket } from 'socket.io-client';
import {
  onMounted,
  onUnmounted,
  ref,
  shallowRef,
  watch,
  type Ref,
} from 'vue';
import type {
  ConstantPartyChatMessage,
  ConstantPartyChatReceiptSummary,
} from '@/shared/api/constantPartiesApi';

export type ConstantPartyChatOnlineCharacter = {
  id: number;
  name: string;
  avatarUrl: string | null;
};

export type UseConstantPartyChatSocketOptions = {
  enabled: Ref<boolean>;
  partyId: Ref<number>;
  characterId: Ref<number | null>;
  getToken: () => Promise<string>;
  onMessageCreated?: (message: ConstantPartyChatMessage) => void;
  onReceiptsChanged?: (messages: ConstantPartyChatReceiptSummary[]) => void;
  onMessageDeleted?: (messageId: number) => void;
};

function readSocketUrl(): { configured: boolean; url: string | undefined } {
  const rawEnv = (import.meta.env.VITE_SOCKET_URL as string | undefined)?.trim() ?? '';
  const syncOff = rawEnv === 'off' || rawEnv === 'false';
  if (syncOff) return { configured: false, url: undefined };
  return { configured: true, url: rawEnv.length > 0 ? rawEnv : undefined };
}

function normalizeId(raw: unknown): number | null {
  const value = Number(raw);
  if (!Number.isFinite(value) || value <= 0) return null;
  return value;
}

export function useConstantPartyChatSocket(options: UseConstantPartyChatSocketOptions) {
  const socketRef = shallowRef<Socket | null>(null);
  const connected = ref(false);
  const authenticated = ref(false);
  const onlineCharacters = ref<ConstantPartyChatOnlineCharacter[]>([]);
  const joinedPartyId = ref<number | null>(null);
  const { configured, url } = readSocketUrl();
  let joinRequestId = 0;

  function leave() {
    const socket = socketRef.value;
    if (socket?.connected && joinedPartyId.value) {
      socket.emit('constant-party-chat:leave', { partyId: joinedPartyId.value });
    }
    joinedPartyId.value = null;
    authenticated.value = false;
    onlineCharacters.value = [];
  }

  async function joinCurrent() {
    const requestId = ++joinRequestId;
    const socket = socketRef.value;
    const partyId = normalizeId(options.partyId.value);
    const characterId = normalizeId(options.characterId.value);
    if (!options.enabled.value || !socket?.connected || !partyId || !characterId) return;

    leave();

    try {
      const token = await options.getToken();
      if (
        requestId !== joinRequestId
        || !socket.connected
        || partyId !== normalizeId(options.partyId.value)
        || characterId !== normalizeId(options.characterId.value)
      ) {
        return;
      }
      joinedPartyId.value = partyId;
      socket.emit('constant-party-chat:join', { token });
    } catch {
      authenticated.value = false;
    }
  }

  onMounted(() => {
    if (!configured || !options.enabled.value || import.meta.env.SSR) return;

    void import('socket.io-client').then(({ io }) => {
      const socket = io(url, {
        transports: ['websocket', 'polling'],
        path: '/socket.io',
        autoConnect: true,
      });
      socketRef.value = socket;

      socket.on('connect', () => {
        connected.value = true;
        void joinCurrent();
      });

      socket.on('disconnect', () => {
        connected.value = false;
        authenticated.value = false;
        joinedPartyId.value = null;
        onlineCharacters.value = [];
      });

      socket.on('constant-party-chat:auth-error', () => {
        authenticated.value = false;
        onlineCharacters.value = [];
      });

      socket.on('constant-party-chat:presence', (event: {
        partyId?: number;
        characters?: ConstantPartyChatOnlineCharacter[];
      }) => {
        if (normalizeId(event?.partyId) !== joinedPartyId.value) return;
        authenticated.value = true;
        onlineCharacters.value = Array.isArray(event.characters)
          ? event.characters.filter((character) => normalizeId(character?.id) !== null)
          : [];
      });

      socket.on('constant-party-chat:message-created', (event: {
        partyId?: number;
        message?: ConstantPartyChatMessage;
      }) => {
        if (normalizeId(event?.partyId) !== joinedPartyId.value || !event.message) return;
        options.onMessageCreated?.(event.message);
      });

      socket.on('constant-party-chat:receipts-changed', (event: {
        partyId?: number;
        messages?: ConstantPartyChatReceiptSummary[];
      }) => {
        if (normalizeId(event?.partyId) !== joinedPartyId.value || !Array.isArray(event.messages)) return;
        options.onReceiptsChanged?.(event.messages);
      });

      socket.on('constant-party-chat:message-deleted', (event: {
        partyId?: number;
        messageId?: number;
      }) => {
        const messageId = normalizeId(event?.messageId);
        if (normalizeId(event?.partyId) !== joinedPartyId.value || !messageId) return;
        options.onMessageDeleted?.(messageId);
      });
    });
  });

  onUnmounted(() => {
    joinRequestId += 1;
    leave();
    socketRef.value?.disconnect();
    socketRef.value = null;
  });

  watch(
    () => [
      options.enabled.value,
      normalizeId(options.partyId.value),
      normalizeId(options.characterId.value),
    ] as const,
    ([enabled]) => {
      if (!enabled) {
        leave();
        return;
      }
      void joinCurrent();
    },
  );

  return {
    socketConfigured: configured,
    connected,
    authenticated,
    onlineCharacters,
  };
}
