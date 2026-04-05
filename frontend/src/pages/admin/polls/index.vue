<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import { Badge, Button, Label } from '@/shared/ui';
import { formatRelativeTime } from '@/shared/lib/relativeTime';
import { guildsApi, type AdminPollItem } from '@/shared/api/guildsApi';
import { useAuthStore } from '@/stores/auth';
import { PERMISSION_DELETE_POLL } from '@/shared/api/authApi';
import { cn } from '@/shared/lib/utils';

const router = useRouter();
const auth = useAuthStore();

const canDelete = computed(() => auth.hasPermission(PERMISSION_DELETE_POLL));

const polls = ref<AdminPollItem[]>([]);
const meta = ref<{ current_page: number; last_page: number; per_page: number; total: number } | null>(null);
const loading = ref(true);
const deleteDialog = ref<{ open: boolean; poll: AdminPollItem | null }>({ open: false, poll: null });
const deleteReason = ref('');
const deleteSubmitting = ref(false);

async function loadPolls(page = 1) {
  loading.value = true;
  try {
    const res = await guildsApi.getAdminPolls({
      page,
      per_page: 20,
    });
    polls.value = res.data;
    meta.value = res.meta;
  } catch {
    polls.value = [];
    meta.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(() => loadPolls());

function pollLink(poll: AdminPollItem) {
  return {
    name: 'guild-polls' as const,
    params: { id: String(poll.guild_id) },
  };
}

function goToPoll(poll: AdminPollItem) {
  router.push(pollLink(poll));
}

function openDeleteDialog(poll: AdminPollItem) {
  deleteDialog.value = { open: true, poll };
  deleteReason.value = '';
}

function closeDeleteDialog() {
  deleteDialog.value = { open: false, poll: null };
  deleteReason.value = '';
}

async function confirmDelete() {
  const poll = deleteDialog.value.poll;
  if (!poll || !canDelete.value) {
    closeDeleteDialog();
    return;
  }
  deleteSubmitting.value = true;
  try {
    await guildsApi.deleteAdminPoll(poll.id, deleteReason.value);
    polls.value = polls.value.filter((p) => p.id !== poll.id);
    if (meta.value) meta.value.total = Math.max(0, meta.value.total - 1);
    closeDeleteDialog();
  } finally {
    deleteSubmitting.value = false;
  }
}

const currentPage = computed(() => meta.value?.current_page ?? 1);
const lastPage = computed(() => meta.value?.last_page ?? 1);
const total = computed(() => meta.value?.total ?? 0);
</script>

<template>
  <div class="container py-6 space-y-4 max-w-3xl mx-auto">
    <h1 class="text-xl font-semibold">Голосования</h1>
    <p class="text-sm text-muted-foreground">
      Все голосования гильдий. Просмотр доступен с правом «Просматривать голосования», удаление — с правом «Удалять голосование».
    </p>

    <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
    <p v-else-if="polls.length === 0" class="text-sm text-muted-foreground">
      Голосований пока нет.
    </p>
    <ul v-else class="space-y-4">
      <li
        v-for="poll in polls"
        :key="poll.id"
        class="rounded-lg border border-border bg-card p-4 space-y-2"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <button
              type="button"
              class="text-left font-medium hover:underline focus:outline-none focus:underline"
              @click="goToPoll(poll)"
            >
              {{ poll.title || 'Без названия' }}
            </button>
            <div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-muted-foreground">
              <span v-if="poll.guild">{{ poll.guild.name }}</span>
              <Badge v-if="poll.is_closed" variant="secondary" class="text-xs">Закрыто</Badge>
              <Badge v-else variant="outline" class="text-xs">Активно</Badge>
              <span>{{ formatRelativeTime(poll.created_at ?? '') }}</span>
              <span v-if="poll.creator_character">{{ poll.creator_character.name }}</span>
            </div>
            <p v-if="poll.description" class="mt-2 text-sm text-muted-foreground line-clamp-2">
              {{ poll.description }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ poll.total_votes }} {{ poll.total_votes === 1 ? 'голос' : 'голосов' }}, {{ poll.options?.length ?? 0 }} вариантов
            </p>
          </div>
          <Button
            v-if="canDelete"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive"
            title="Удалить голосование"
            @click="openDeleteDialog(poll)"
          >
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 6h18" />
              <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
              <line x1="10" x2="10" y1="11" y2="17" />
              <line x1="14" x2="14" y1="11" y2="17" />
            </svg>
          </Button>
        </div>
      </li>
    </ul>

    <div v-if="meta && lastPage > 1" class="flex items-center justify-center gap-2 pt-4">
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage <= 1 || loading"
        @click="loadPolls(currentPage - 1)"
      >
        Назад
      </Button>
      <span class="text-sm text-muted-foreground">
        {{ currentPage }} / {{ lastPage }} (всего {{ total }})
      </span>
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage >= lastPage || loading"
        @click="loadPolls(currentPage + 1)"
      >
        Вперёд
      </Button>
    </div>

    <DialogRoot :open="deleteDialog.open" @update:open="(v: boolean) => { if (!v) closeDeleteDialog(); }">
      <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-50 w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
          :aria-describedby="undefined"
        >
          <DialogTitle class="text-lg font-semibold">
            Удалить голосование
          </DialogTitle>
          <DialogDescription class="text-sm text-muted-foreground space-y-4">
            <p>
              Голосование «{{ deleteDialog.poll?.title || 'Без названия' }}» будет удалено. Автор получит уведомление с указанием причины.
            </p>
            <div class="space-y-2">
              <Label for="delete-reason">Причина удаления (опционально)</Label>
              <textarea
                id="delete-reason"
                v-model="deleteReason"
                :class="cn(
                  'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm resize-y',
                )"
                placeholder="Укажите причину удаления для уведомления автору…"
                maxlength="1000"
                rows="3"
              />
            </div>
          </DialogDescription>
          <div class="flex justify-end gap-2 pt-4">
            <Button variant="outline" :disabled="deleteSubmitting" @click="closeDeleteDialog">
              Отмена
            </Button>
            <Button
              variant="destructive"
              :disabled="deleteSubmitting"
              @click="confirmDelete"
            >
              {{ deleteSubmitting ? '…' : 'Удалить' }}
            </Button>
          </div>
        </DialogContent>
      </DialogPortal>
      </ClientOnly>
    </DialogRoot>
  </div>
</template>
