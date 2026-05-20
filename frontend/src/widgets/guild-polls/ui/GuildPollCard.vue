<script setup lang="ts">
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Button,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import type { GuildPollItem, GuildPollOptionItem } from '@/shared/api/guildsApi';

interface PollVoteCharacter {
  id: number;
  name: string;
}

const props = defineProps<{
  poll: GuildPollItem;
  myCharacters: PollVoteCharacter[];
  canEdit: boolean;
  canClose: boolean;
  canReset: boolean;
  canDelete: boolean;
  voteCharacterId: number | null;
  voteOptionId: number | null;
  voteLoading: boolean;
}>();

const emit = defineEmits<{
  (e: 'edit', poll: GuildPollItem): void;
  (e: 'close', poll: GuildPollItem): void;
  (e: 'reset', poll: GuildPollItem): void;
  (e: 'delete', poll: GuildPollItem): void;
  (e: 'vote', poll: GuildPollItem, optionId: number | null): void;
  (e: 'update:voteCharacterId', value: number | null): void;
}>();

function formatEndsAt(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatPollDateTime(iso: string | null): string {
  if (!iso) return 'Не указано';
  const d = new Date(iso);
  return d.toLocaleString(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function optionVotePercent(option: GuildPollOptionItem, total: number): number {
  if (total === 0) return 0;
  return Math.round((option.votes_count / total) * 100);
}
</script>

<template>
  <Card class="overflow-hidden rounded-lg border border-border/80 shadow-sm">
    <CardHeader class="space-y-2 pb-2">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
          <div class="flex min-w-0 items-center gap-2">
            <CardTitle class="truncate text-base">{{ poll.title }}</CardTitle>
            <span
              class="inline-flex h-5 shrink-0 items-center rounded-md px-2 text-xs font-medium"
              :class="poll.is_closed ? 'bg-muted text-muted-foreground' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'"
            >
              {{ poll.is_closed ? 'Закрыто' : 'Открыто' }}
            </span>
          </div>
          <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span class="inline-flex items-center gap-1">
              <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M8 2v4" />
                <path d="M16 2v4" />
                <rect width="18" height="18" x="3" y="4" rx="2" />
                <path d="M3 10h18" />
              </svg>
              Создано: {{ formatPollDateTime(poll.created_at) }}
            </span>
            <span class="inline-flex items-center gap-1">
              <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 6v6l4 2" />
              </svg>
              Окончание: {{ poll.ends_at ? formatEndsAt(poll.ends_at) : 'без срока' }}
            </span>
            <span
              class="inline-flex items-center gap-1"
              :title="poll.is_anonymous ? 'Анонимное голосование: участники не видят, кто за какой вариант проголосовал.' : 'Открытое голосование: участники видят, кто за какой вариант проголосовал.'"
            >
              <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <template v-if="poll.is_anonymous">
                  <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                  <circle cx="12" cy="12" r="3" />
                  <path d="m2 2 20 20" />
                </template>
                <template v-else>
                  <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                  <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </template>
              </svg>
              {{ poll.is_anonymous ? 'Анонимное' : 'Открытое' }}
            </span>
          </div>
          <p
            v-if="poll.description"
            class="mt-1 text-sm leading-5 text-muted-foreground"
          >
            {{ poll.description }}
          </p>
        </div>
        <div v-if="canEdit || canClose || canReset || canDelete" class="flex flex-wrap gap-1.5">
          <Button
            v-if="canEdit && !poll.is_closed"
            variant="outline"
            size="sm"
            class="h-8 w-8 p-0"
            title="Редактировать"
            aria-label="Редактировать"
            @click="emit('edit', poll)"
          >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 20h9" />
              <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
            </svg>
          </Button>
          <Button
            v-if="canClose && !poll.is_closed"
            variant="outline"
            size="sm"
            class="h-8 w-8 p-0"
            title="Закрыть"
            aria-label="Закрыть"
            @click="emit('close', poll)"
          >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
          </Button>
          <Button
            v-if="canReset"
            variant="outline"
            size="sm"
            class="h-8"
            @click="emit('reset', poll)"
          >
            Сбросить
          </Button>
          <Button
            v-if="canDelete"
            variant="outline"
            size="sm"
            class="h-8 w-8 p-0 text-destructive hover:text-destructive"
            title="Удалить"
            aria-label="Удалить"
            @click="emit('delete', poll)"
          >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 6h18" />
              <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
              <path d="M10 11v6" />
              <path d="M14 11v6" />
            </svg>
          </Button>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
        <div
          v-if="!poll.is_closed && myCharacters.length > 1"
          class="inline-flex items-center gap-2"
        >
          <span class="inline-flex items-center gap-1">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
            Голосовать от имени:
          </span>
          <SelectRoot
            :model-value="voteCharacterId != null ? String(voteCharacterId) : ''"
            @update:model-value="(v) => emit('update:voteCharacterId', v ? Number(v) : null)"
          >
            <SelectTrigger class="h-8 w-32 text-xs">
              <SelectValue placeholder="Персонаж" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="c in myCharacters"
                :key="c.id"
                :value="String(c.id)"
              >
                {{ c.name }}
              </SelectItem>
            </SelectContent>
          </SelectRoot>
        </div>
      </div>
    </CardHeader>
    <CardContent class="space-y-2 pt-0">
      <div class="overflow-hidden rounded-md border border-border/80">
        <div
          v-for="opt in poll.options"
          :key="opt.id"
          class="border-b border-border/70 last:border-b-0"
        >
          <button
            v-if="!poll.is_closed && myCharacters.length > 0"
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 border-l-4 px-2.5 py-1.5 text-left text-sm transition-colors hover:bg-muted/60 disabled:opacity-70"
            :class="voteOptionId === opt.id ? 'border-l-primary bg-primary/10' : 'border-l-transparent bg-background'"
            :disabled="voteLoading"
            @click="emit('vote', poll, voteOptionId === opt.id ? null : opt.id)"
          >
            <span
              class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
              :class="voteOptionId === opt.id ? 'border-primary bg-primary' : 'border-muted-foreground'"
            >
              <span v-if="voteOptionId === opt.id" class="h-1.5 w-1.5 rounded-full bg-primary-foreground" />
            </span>
            <span class="min-w-0 flex-1 truncate">{{ opt.text }}</span>
            <span class="shrink-0 text-muted-foreground">{{ opt.votes_count }} ({{ optionVotePercent(opt, poll.total_votes) }}%)</span>
          </button>
          <template v-else>
            <div
              class="flex items-center justify-between gap-2 border-l-4 px-2.5 py-1.5 text-sm"
              :class="voteOptionId === opt.id ? 'border-l-muted-foreground bg-muted/70' : 'border-l-transparent bg-background'"
            >
              <span class="min-w-0 truncate">{{ opt.text }}</span>
              <span class="text-muted-foreground">{{ opt.votes_count }} ({{ optionVotePercent(opt, poll.total_votes) }}%)</span>
            </div>
          </template>
          <div class="h-1 overflow-hidden bg-muted">
            <div
              class="h-full transition-all"
              :class="poll.is_closed ? 'bg-muted-foreground/50' : 'bg-primary'"
              :style="{ width: `${optionVotePercent(opt, poll.total_votes)}%` }"
            />
          </div>
          <div
            v-if="!poll.is_anonymous && opt.voters?.length"
            class="flex flex-wrap items-center gap-1.5 border-t border-border/60 bg-muted/30 px-2.5 py-1.5 text-xs text-muted-foreground"
          >
            <span>Проголосовали:</span>
            <span
              v-for="voter in opt.voters"
              :key="`${opt.id}-${voter.character_id}`"
              class="inline-flex h-5 max-w-36 items-center rounded-md border bg-background px-2 text-foreground"
            >
              <span class="truncate">{{ voter.name }}</span>
            </span>
          </div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
        <span>Всего голосов: {{ poll.total_votes }}</span>
      </div>
    </CardContent>
  </Card>
</template>
