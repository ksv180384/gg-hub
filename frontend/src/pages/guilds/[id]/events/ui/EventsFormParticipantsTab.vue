<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  Card,
  CardHeader,
  CardTitle,
  CardContent,
  Button,
  Input,
  Label,
  Tooltip,
} from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import { cn } from '@/shared/lib/utils';
import type { GuildRosterMember } from '@/shared/api/guildsApi';
import type { EventsFormParticipant } from '../events-form-types';
import {
  calculateEventParticipantDkpPoints,
  parseDkpBasePointsInput,
} from '@/shared/lib/calculateEventParticipantDkpPoints';

const props = defineProps<{
  dkpEnabled: boolean;
  dkpBasePoints: string;
  externalNickname: string;
  roster: GuildRosterMember[];
  loadingRoster: boolean;
  importParticipantsLoading: boolean;
  importParticipantsError: string;
  participantsExcelImportHint: string;
  guildParticipants: EventsFormParticipant[];
  externalParticipants: EventsFormParticipant[];
  hasParticipants: boolean;
  totalParticipantsCount: number;
  isMemberSelected: (member: GuildRosterMember) => boolean;
}>();

const emit = defineEmits<{
  (e: 'update:externalNickname', value: string): void;
  (e: 'addExternalParticipant'): void;
  (e: 'participantsXlsxChange', event: Event): void;
  (e: 'removeParticipant', participant: EventsFormParticipant): void;
  (e: 'toggleGuildParticipant', characterId: number): void;
}>();

const participantsXlsxInputRef = ref<HTMLInputElement | null>(null);
const rosterSearch = ref('');

const AVATAR_TONES = [
  'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
  'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200',
  'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-200',
  'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
  'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
] as const;

function openParticipantsXlsxPicker() {
  participantsXlsxInputRef.value?.click();
}

function avatarFallback(name: string): string {
  const trimmed = name.trim();
  return trimmed ? trimmed.charAt(0).toUpperCase() : '?';
}

function avatarToneClass(name: string): string {
  let hash = 0;
  for (let i = 0; i < name.length; i += 1) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return AVATAR_TONES[Math.abs(hash) % AVATAR_TONES.length];
}

function memberSubtitle(member: GuildRosterMember): string | null {
  if (member.guild_role?.name) {
    return member.guild_role.name;
  }
  const firstClass = member.game_classes[0];
  if (firstClass) {
    return (firstClass.name_ru ?? firstClass.name).trim() || null;
  }
  return null;
}

function findRosterMember(characterId: number | null | undefined): GuildRosterMember | undefined {
  if (characterId == null) return undefined;
  return props.roster.find((m) => m.character_id === characterId);
}

const filteredRosterForAdd = computed(() => {
  const q = rosterSearch.value.trim().toLowerCase();
  return props.roster.filter((member) => {
    if (props.isMemberSelected(member)) return false;
    if (!q) return true;
    return member.name.trim().toLowerCase().includes(q);
  });
});

const confirmedParticipantRows = computed(() => {
  const guildRows = props.guildParticipants.map((participant) => {
    const member = findRosterMember(participant.character_id);
    return {
      participant,
      name: member?.name ?? `Персонаж #${participant.character_id}`,
      subtitle: member ? memberSubtitle(member) : null,
      avatarUrl: member?.avatar_url ?? null,
      isExternal: false,
    };
  });

  const externalRows = props.externalParticipants.map((participant) => ({
    participant,
    name: participant.external_name ?? '',
    subtitle: 'Сторонний участник',
    avatarUrl: null as string | null,
    isExternal: true,
  }));

  return [...guildRows, ...externalRows];
});

function parseOverrideValue(value: string | number): number | null {
  const raw = String(value).trim();
  if (!raw) return null;
  const num = Number(raw);
  if (!Number.isFinite(num) || num < 0) return null;
  return Math.trunc(num);
}

const eventDkpBasePoints = computed(() => parseDkpBasePointsInput(props.dkpBasePoints));

function previewParticipantDkpPoints(participant: EventsFormParticipant): number | null {
  return calculateEventParticipantDkpPoints(
    eventDkpBasePoints.value,
    participant.dkp_coefficient ?? 1,
    participant.dkp_points_override ?? null
  );
}

function formatDkpPreview(points: number | null): string {
  if (points == null) return '—';
  return String(points);
}
</script>

<template>
  <Card>
    <CardHeader class="space-y-1 pb-4">
      <CardTitle class="text-base">Участники события</CardTitle>
      <p class="text-sm text-muted-foreground">
        Добавьте участников, которые подтвердили участие
      </p>
    </CardHeader>

    <CardContent class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center">
          <Input
            id="external-nick"
            :model-value="externalNickname"
            type="text"
            placeholder="Ник участника"
            class="min-w-0 flex-1"
            @update:model-value="emit('update:externalNickname', String($event))"
            @keydown.enter.prevent="emit('addExternalParticipant')"
          />
          <Button
            type="button"
            class="shrink-0 sm:px-6"
            @click="emit('addExternalParticipant')"
          >
            Добавить
          </Button>
        </div>

        <input
          ref="participantsXlsxInputRef"
          type="file"
          accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          class="sr-only"
          @change="emit('participantsXlsxChange', $event)"
        />
        <Tooltip
          :content="participantsExcelImportHint"
          side="top"
          class="max-w-sm text-left"
        >
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="importParticipantsLoading || loadingRoster"
            class="w-full shrink-0 gap-2 sm:w-auto"
            @click="openParticipantsXlsxPicker"
          >
            {{ importParticipantsLoading ? 'Читаем…' : 'Загрузить из Excel' }}
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="size-4 shrink-0"
              aria-hidden="true"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="17 8 12 3 7 8" />
              <line x1="12" x2="12" y1="3" y2="15" />
            </svg>
          </Button>
        </Tooltip>
      </div>

      <p v-if="importParticipantsError" class="text-xs text-destructive">
        {{ importParticipantsError }}
      </p>

      <section class="space-y-3">
        <h3 class="text-sm font-semibold">
          Приняли участие
          <span v-if="totalParticipantsCount">({{ totalParticipantsCount }})</span>
        </h3>

        <p
          v-if="!hasParticipants"
          class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
        >
          Пока никто не добавлен.
        </p>

        <ul v-else class="space-y-2">
          <li
            v-for="row in confirmedParticipantRows"
            :key="row.isExternal ? `ext-${row.name}` : `char-${row.participant.character_id}`"
            :class="cn(
              'flex flex-wrap items-center gap-3 rounded-lg border bg-card px-3 py-2.5',
              row.isExternal && 'border-amber-500/40 bg-amber-500/5',
            )"
          >
            <Avatar
              v-if="row.avatarUrl"
              :src="row.avatarUrl"
              :alt="row.name"
              :fallback="avatarFallback(row.name)"
              class="h-10 w-10 rounded-full"
            />
            <div
              v-else
              :class="cn(
                'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                avatarToneClass(row.name),
              )"
              aria-hidden="true"
            >
              {{ avatarFallback(row.name) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium">{{ row.name }}</p>
              <p v-if="row.subtitle" class="truncate text-xs text-muted-foreground">
                {{ row.subtitle }}
              </p>
            </div>
            <div
              v-if="dkpEnabled && !row.isExternal"
              class="flex w-full shrink-0 flex-wrap items-center gap-1.5 sm:w-auto"
            >
              <div class="flex items-center gap-0.5">
                <Tooltip content="Коэффициент" side="top">
                  <span class="cursor-default text-[10px] text-muted-foreground">Коэф.</span>
                </Tooltip>
                <Label
                  :for="`dkp-coef-${row.participant.character_id ?? row.name}`"
                  class="sr-only"
                >
                  Коэффициент
                </Label>
                <Input
                  :id="`dkp-coef-${row.participant.character_id ?? row.name}`"
                  v-model.number="row.participant.dkp_coefficient"
                  type="number"
                  min="0"
                  step="0.1"
                  title="Коэффициент"
                  class="h-7 w-14 px-1.5 text-center text-xs tabular-nums"
                />
              </div>
              <div class="flex items-center gap-1">
                <Label
                  :for="`dkp-override-${row.participant.character_id ?? row.name}`"
                  class="sr-only"
                >
                  Коррекция
                </Label>
                <Input
                  :id="`dkp-override-${row.participant.character_id ?? row.name}`"
                  :model-value="row.participant.dkp_points_override ?? ''"
                  type="number"
                  min="0"
                  step="1"
                  title="Коррекция (override)"
                  class="h-7 w-14 px-1.5 text-center text-xs tabular-nums"
                  placeholder="—"
                  @update:model-value="
                    row.participant.dkp_points_override = parseOverrideValue($event)
                  "
                />
              </div>
              <span
                class="min-w-[4.5rem] text-xs tabular-nums"
                :class="
                  previewParticipantDkpPoints(row.participant) != null
                    ? 'font-medium text-foreground'
                    : 'text-muted-foreground'
                "
                :title="
                  row.participant.dkp_points_override != null
                    ? 'Итог по коррекции'
                    : eventDkpBasePoints == null
                      ? 'Укажите очки ДКП за посещение на вкладке «Информация»'
                      : 'База × коэффициент'
                "
              >
                {{ formatDkpPreview(previewParticipantDkpPoints(row.participant)) }} очк.
              </span>
            </div>

            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="size-8 shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
              :aria-label="`Удалить ${row.name}`"
              @click="emit('removeParticipant', row.participant)"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-4"
                aria-hidden="true"
              >
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
              </svg>
            </Button>
          </li>
        </ul>
      </section>

      <section class="space-y-3 border-t pt-6">
        <h3 class="text-sm font-semibold">Список гильдии</h3>

        <div class="relative">
          <Input
            v-model="rosterSearch"
            type="search"
            placeholder="Поиск по никнейму..."
            class="pr-9"
            :disabled="loadingRoster"
          />
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
        </div>

        <p v-if="loadingRoster" class="text-sm text-muted-foreground">
          Загрузка состава...
        </p>
        <p v-else-if="!roster.length" class="text-sm text-muted-foreground">
          Нет данных о составе.
        </p>
        <template v-else>
          <ul
            class="max-h-[280px] space-y-1 overflow-y-auto rounded-lg border p-1"
            role="list"
          >
            <li
              v-for="member in filteredRosterForAdd"
              :key="member.character_id"
              class="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/50"
            >
              <Avatar
                :src="member.avatar_url ?? undefined"
                :alt="member.name"
                :fallback="avatarFallback(member.name)"
                class="h-9 w-9 rounded-full bg-muted"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ member.name }}</p>
                <p
                  v-if="memberSubtitle(member)"
                  class="truncate text-xs text-muted-foreground"
                >
                  {{ memberSubtitle(member) }}
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                class="shrink-0 border-primary/20 bg-primary/5 text-primary hover:bg-primary/10 hover:text-primary"
                @click="emit('toggleGuildParticipant', member.character_id)"
              >
                Добавить
              </Button>
            </li>
            <li
              v-if="filteredRosterForAdd.length === 0"
              class="px-3 py-6 text-center text-sm text-muted-foreground"
            >
              {{
                rosterSearch.trim()
                  ? 'Никого не найдено'
                  : 'Все участники гильдии уже добавлены'
              }}
            </li>
          </ul>
          <p class="text-xs text-muted-foreground">
            Всего участников в гильдии: {{ roster.length }}
          </p>
        </template>
      </section>
    </CardContent>
  </Card>
</template>
