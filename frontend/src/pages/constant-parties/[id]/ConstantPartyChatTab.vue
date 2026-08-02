<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import {
  Check,
  CheckCheck,
  MessageCircle,
  Send,
  WifiOff,
} from '@lucide/vue';
import { Avatar, Button, Spinner } from '@/shared/ui';
import type { ConstantPartyChatMessage } from '@/shared/api/constantPartiesApi';
import type { ConstantPartyChatOnlineCharacter } from '@/shared/lib/useConstantPartyChatSocket';

const props = defineProps<{
  messages: ConstantPartyChatMessage[];
  currentCharacterId: number | null;
  onlineCharacters: ConstantPartyChatOnlineCharacter[];
  socketConnected: boolean;
  socketAuthenticated: boolean;
  loading: boolean;
  sending: boolean;
  draft: string;
}>();

const emit = defineEmits<{
  (event: 'update:draft', value: string): void;
  (event: 'send'): void;
}>();

const messagesViewport = ref<HTMLElement | null>(null);
const stickToBottom = ref(true);

function initials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('') || '??';
}

function isMine(message: ConstantPartyChatMessage): boolean {
  return message.character_id === props.currentCharacterId;
}

function formatTime(value: string): string {
  return new Intl.DateTimeFormat('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

function dateKey(value: string): string {
  const date = new Date(value);
  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function formatDay(value: string): string {
  const date = new Date(value);
  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);

  if (dateKey(value) === dateKey(today.toISOString())) return 'Сегодня';
  if (dateKey(value) === dateKey(yesterday.toISOString())) return 'Вчера';

  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric',
  }).format(date);
}

function showDay(index: number): boolean {
  const message = props.messages[index];
  const previousMessage = props.messages[index - 1];
  if (!message) return false;
  if (!previousMessage) return true;
  return dateKey(message.created_at) !== dateKey(previousMessage.created_at);
}

function deliveryTitle(message: ConstantPartyChatMessage): string {
  if (message.delivery_status === 'read') {
    return `Прочитано: ${message.read_count} из ${message.recipient_count}`;
  }
  if (message.delivery_status === 'delivered') {
    return `Доставлено: ${message.delivered_count} из ${message.recipient_count}`;
  }
  return 'Отправлено';
}

function onScroll() {
  const viewport = messagesViewport.value;
  if (!viewport) return;
  stickToBottom.value = viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight < 96;
}

async function scrollToBottom(behavior: ScrollBehavior = 'auto') {
  await nextTick();
  const viewport = messagesViewport.value;
  if (!viewport) return;
  viewport.scrollTo({ top: viewport.scrollHeight, behavior });
}

function onComposerKeydown(event: KeyboardEvent) {
  if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
  event.preventDefault();
  if (props.draft.trim() && !props.sending) {
    emit('send');
  }
}

watch(
  () => props.messages.length,
  (length, previousLength) => {
    if (previousLength === 0 || stickToBottom.value) {
      void scrollToBottom(previousLength === 0 ? 'auto' : 'smooth');
    }
  },
);

onMounted(() => {
  void scrollToBottom();
});
</script>

<template>
  <section class="overflow-hidden rounded-lg border bg-background">
    <header class="border-b bg-card px-4 py-3">
      <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
          <h2 class="text-sm font-semibold">Чат КП</h2>
          <p
            class="mt-0.5 flex items-center gap-1.5 text-xs"
            :class="socketConnected && socketAuthenticated ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'"
          >
            <span
              v-if="socketConnected && socketAuthenticated"
              class="size-1.5 rounded-full bg-emerald-500"
            />
            <WifiOff v-else class="size-3" />
            {{ socketConnected && socketAuthenticated ? `${onlineCharacters.length} онлайн` : 'Переподключение…' }}
          </p>
        </div>

        <div
          v-if="onlineCharacters.length > 0"
          class="flex max-w-[65%] items-center justify-end gap-3 overflow-x-auto"
        >
          <div
            v-for="character in onlineCharacters"
            :key="character.id"
            class="flex shrink-0 items-center gap-1.5"
            :title="`${character.name} онлайн`"
          >
            <span class="relative">
              <Avatar
                class="size-7 rounded-full"
                :src="character.avatarUrl ?? undefined"
                :alt="character.name"
                :fallback="initials(character.name)"
              />
              <span class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border-2 border-card bg-emerald-500" />
            </span>
            <span class="hidden max-w-28 truncate text-xs text-muted-foreground lg:block">
              {{ character.name }}
            </span>
          </div>
        </div>
      </div>
    </header>

    <div
      ref="messagesViewport"
      class="h-[55dvh] min-h-96 max-h-[42rem] overflow-y-auto bg-muted/20 px-3 py-4 sm:px-5"
      @scroll="onScroll"
    >
      <div v-if="loading" class="flex h-full items-center justify-center">
        <Spinner class="size-7" />
      </div>

      <div
        v-else-if="messages.length === 0"
        class="flex h-full flex-col items-center justify-center text-center text-muted-foreground"
      >
        <MessageCircle class="mb-3 size-8" />
        <p class="text-sm font-medium text-foreground">Сообщений пока нет</p>
        <p class="mt-1 text-xs">Начните разговор с участниками КП.</p>
      </div>

      <div v-else class="space-y-2">
        <template
          v-for="(message, index) in messages"
          :key="message.id"
        >
          <div
            v-if="showDay(index)"
            class="flex justify-center py-2"
          >
            <span class="rounded-full bg-background/90 px-3 py-1 text-xs text-muted-foreground shadow-sm ring-1 ring-border">
              {{ formatDay(message.created_at) }}
            </span>
          </div>

          <div
            class="flex items-end gap-2"
            :class="isMine(message) ? 'justify-end' : 'justify-start'"
          >
            <Avatar
              v-if="!isMine(message)"
              class="size-8 rounded-full"
              :src="message.character?.avatar_url ?? undefined"
              :alt="message.character?.name ?? 'Персонаж'"
              :fallback="initials(message.character?.name ?? 'Персонаж')"
            />

            <div
              class="max-w-[82%] rounded-lg px-3 py-2 shadow-sm sm:max-w-[72%]"
              :class="isMine(message) ? 'bg-primary text-primary-foreground' : 'border bg-background text-foreground'"
            >
              <p
                v-if="!isMine(message)"
                class="mb-1 text-xs font-semibold text-primary"
              >
                {{ message.character?.name ?? 'Персонаж' }}
              </p>
              <p class="whitespace-pre-wrap break-words text-sm leading-relaxed">
                {{ message.body }}
              </p>
              <div
                class="mt-1 flex items-center justify-end gap-1 text-[11px]"
                :class="isMine(message) ? 'text-primary-foreground/70' : 'text-muted-foreground'"
              >
                <span>{{ formatTime(message.created_at) }}</span>
                <span
                  v-if="isMine(message)"
                  class="inline-flex"
                  :class="message.delivery_status === 'read' ? 'text-emerald-200' : ''"
                  :title="deliveryTitle(message)"
                >
                  <Check
                    v-if="message.delivery_status === 'sent'"
                    class="size-3.5"
                  />
                  <CheckCheck v-else class="size-3.5" />
                </span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <form
      class="flex items-end gap-2 border-t bg-card p-3"
      @submit.prevent="emit('send')"
    >
      <textarea
        :value="draft"
        rows="1"
        maxlength="5000"
        class="max-h-32 min-h-10 flex-1 resize-none rounded-lg border bg-background px-3 py-2 text-sm leading-5 outline-none transition-shadow placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/20"
        placeholder="Сообщение"
        aria-label="Сообщение"
        @input="emit('update:draft', ($event.target as HTMLTextAreaElement).value)"
        @keydown="onComposerKeydown"
      />
      <Button
        type="submit"
        size="icon"
        class="size-10 shrink-0"
        title="Отправить"
        aria-label="Отправить"
        :disabled="sending || !draft.trim()"
      >
        <Spinner v-if="sending" class="size-4" />
        <Send v-else class="size-4" />
      </Button>
    </form>
  </section>
</template>
