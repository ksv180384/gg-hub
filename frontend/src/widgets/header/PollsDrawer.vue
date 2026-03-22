<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { Badge, Sheet, SelectRoot, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/shared/ui';
import { guildsApi, type UserPollItem } from '@/shared/api/guildsApi';

function optionVotePercent(opt: { votes_count: number }, total: number): number {
  if (total === 0) return 0;
  return Math.round((opt.votes_count / total) * 100);
}

interface Props {
  open: boolean;
  polls: UserPollItem[];
  loading: boolean;
  /** Количество активных голосований, в которых пользователь ещё не проголосовал. */
  unvotedCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
  unvotedCount: 0,
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'poll-updated', poll: UserPollItem): void;
}>();

const open = computed({
  get: () => props.open,
  set: (v: boolean) => emit('update:open', v),
});

const voteCharacterIdByPoll = ref<Record<number, number | null>>({});
const voteOptionIdByPoll = ref<Record<number, number | null>>({});
const voteLoadingByPoll = ref<Record<number, boolean>>({});

function getVoteCharacterId(poll: UserPollItem): number | null {
  const chars = poll.my_characters ?? [];
  if (chars.length === 0) return null;
  if (chars.length === 1) return chars[0].id;
  return voteCharacterIdByPoll.value[poll.id] ?? chars[0]?.id ?? null;
}

async function selectOptionAndVote(poll: UserPollItem, optionId: number | null) {
  const charId = getVoteCharacterId(poll);
  if (charId == null || !poll.guild) return;
  const prevOptionId = voteOptionIdByPoll.value[poll.id];
  if (prevOptionId === optionId) return;

  voteOptionIdByPoll.value = { ...voteOptionIdByPoll.value, [poll.id]: optionId };
  voteLoadingByPoll.value[poll.id] = true;
  try {
    if (optionId == null) {
      await guildsApi.withdrawGuildPollVote(poll.guild_id, poll.id, charId);
    } else {
      await guildsApi.voteGuildPoll(poll.guild_id, poll.id, optionId, charId);
    }
    const updated = await guildsApi.getGuildPoll(poll.guild_id, poll.id);
    emit('poll-updated', { ...updated, guild: poll.guild, my_characters: poll.my_characters });
  } catch {
    voteOptionIdByPoll.value = { ...voteOptionIdByPoll.value, [poll.id]: prevOptionId };
  } finally {
    voteLoadingByPoll.value[poll.id] = false;
  }
}

function setVoteCharacter(pollId: number, characterId: number | null) {
  voteCharacterIdByPoll.value = { ...voteCharacterIdByPoll.value, [pollId]: characterId };
}

const badgeText = computed(() => {
  const n = props.unvotedCount ?? 0;
  if (n <= 0) return '';
  if (n > 9) return '9+';
  return String(n);
});

watch(
  () => props.polls,
  (polls) => {
    const nextOpt: Record<number, number | null> = { ...voteOptionIdByPoll.value };
    const nextChar: Record<number, number | null> = { ...voteCharacterIdByPoll.value };
    for (const poll of polls) {
      nextOpt[poll.id] = poll.my_vote_option_id ?? null;
      const chars = poll.my_characters ?? [];
      if (chars.length > 1) {
        nextChar[poll.id] = nextChar[poll.id] ?? chars[0]?.id ?? null;
      }
    }
    voteOptionIdByPoll.value = nextOpt;
    voteCharacterIdByPoll.value = nextChar;
  },
  { deep: true, immediate: true }
);
</script>

<template>
  <Sheet v-model:open="open" side="right" class="w-full max-w-sm">
    <template #trigger>
      <button
        type="button"
        class="relative flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
        aria-label="Голосования"
      >
        <Badge
          v-if="badgeText"
          variant="destructive"
          class="absolute -right-1 -top-1 flex max-w-[10px] items-center justify-center bg-red-50 text-[10px] text-red-700 hover:text-red-200 dark:bg-red-950 dark:text-red-300"
        >
          {{ badgeText }}
        </Badge>
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          class="h-[1.125rem] w-[1.125rem]"
        >
          <path d="M12 20V10" />
          <path d="M18 20V4" />
          <path d="M6 20v-4" />
        </svg>
      </button>
    </template>
    <template #title>Голосования</template>
    <h2 class="pb-2 text-lg font-semibold">Голосования</h2>
    <div class="flex min-h-0 flex-1 flex-col">
      <div class="flex min-h-0 flex-1 flex-col overflow-y-auto pt-2">
        <p v-if="loading" class="px-2 py-4 text-sm text-muted-foreground">
          Загрузка…
        </p>
        <template v-else-if="polls.length === 0">
          <p class="px-2 py-4 text-sm text-muted-foreground">
            Нет активных голосований
          </p>
        </template>
        <template v-else>
          <div
            v-for="poll in polls"
            :key="`${poll.guild_id}-${poll.id}`"
            class="border-b border-border px-3 py-3 last:border-b-0"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <p class="font-medium">{{ poll.title }}</p>
                <RouterLink
                  v-if="poll.guild"
                  :to="{ name: 'guild-polls', params: { id: String(poll.guild_id) } }"
                  class="text-xs text-primary hover:underline"
                  @click="emit('update:open', false)"
                >
                  {{ poll.guild.name }}
                </RouterLink>
              </div>
              <div class="flex shrink-0 items-center gap-1">
              <Badge v-if="poll.is_closed" variant="secondary" class="text-xs">
                Закрыто
              </Badge>
              <Badge v-else-if="!poll.is_anonymous" variant="outline" class="text-xs">
                Открытое
              </Badge>
            </div>
            </div>
            <div v-if="!poll.is_closed && (poll.my_characters?.length ?? 0) > 1" class="mb-2">
              <SelectRoot
                :model-value="(voteCharacterIdByPoll[poll.id] != null ? String(voteCharacterIdByPoll[poll.id]) : '') || undefined"
                @update:model-value="(v) => setVoteCharacter(poll.id, v ? Number(v) : null)"
              >
                <SelectTrigger class="h-7 w-full max-w-[140px] text-xs">
                  <SelectValue placeholder="Голосовать от имени" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="c in (poll.my_characters ?? [])"
                    :key="c.id"
                    :value="String(c.id)"
                  >
                    {{ c.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
            </div>
            <div class="mt-2 space-y-1.5">
              <div
                v-for="opt in poll.options"
                :key="opt.id"
                class="space-y-1"
              >
                <button
                  v-if="!poll.is_closed && (poll.my_characters?.length ?? 0) > 0"
                  type="button"
                  class="flex w-full cursor-pointer items-center gap-2 rounded-md border px-2 py-1.5 text-left text-xs transition-colors hover:bg-muted disabled:opacity-70"
                  :class="voteOptionIdByPoll[poll.id] === opt.id ? 'border-primary bg-primary/10' : 'border-border'"
                  :disabled="voteLoadingByPoll[poll.id]"
                  @click="selectOptionAndVote(poll, voteOptionIdByPoll[poll.id] === opt.id ? null : opt.id)"
                >
                  <span
                    class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border"
                    :class="voteOptionIdByPoll[poll.id] === opt.id ? 'border-primary bg-primary' : 'border-muted-foreground'"
                  >
                    <span v-if="voteOptionIdByPoll[poll.id] === opt.id" class="h-1.5 w-1.5 rounded-full bg-primary-foreground" />
                  </span>
                  <span class="min-w-0 flex-1 truncate">{{ opt.text }}</span>
                  <span class="shrink-0 text-muted-foreground">{{ opt.votes_count }} ({{ optionVotePercent(opt, poll.total_votes) }}%)</span>
                </button>
                <template v-else>
                  <div class="flex items-center justify-between gap-1 text-xs">
                    <span class="truncate">{{ opt.text }}</span>
                    <span class="shrink-0 text-muted-foreground">{{ opt.votes_count }} ({{ optionVotePercent(opt, poll.total_votes) }}%)</span>
                  </div>
                </template>
                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                  <div
                    class="h-full bg-primary transition-all"
                    :style="{ width: `${optionVotePercent(opt, poll.total_votes)}%` }"
                  />
                </div>
                <p
                  v-if="!poll.is_anonymous && opt.voters?.length"
                  class="text-[10px] text-muted-foreground truncate"
                  :title="opt.voters.map((v) => v.name).join(', ')"
                >
                  {{ opt.voters.map((v) => v.name).join(', ') }}
                </p>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </Sheet>
</template>
