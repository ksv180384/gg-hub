<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  Avatar,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Input,
  Label,
  Tooltip,
} from '@/shared/ui';
import {
  avatarFallback,
  type GuildRouletteModel,
} from '@/features/guild-roulette';
import type { GuildRosterMember } from '@/shared/api/guildsApi';
import CharacterPickerDialog from './CharacterPickerDialog.vue';

const props = defineProps<{
  model: GuildRouletteModel;
}>();

const wheelExcelImportHint =
  'Первый столбец — один ник в строке. Если ник совпадает с составом гильдии (без учёта регистра), на колесо попадет персонаж из гильдии; иначе — только текст ника. Можно также ввести ник вручную выше.';

const wheelExcelInputRef = ref<HTMLInputElement | null>(null);

function openWheelExcelPicker() {
  props.model.clearImportWheelExcelError();
  wheelExcelInputRef.value?.click();
}

async function onWheelExcelChange(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;
  await props.model.importWheelExcelFromFile(file);
}

/** Член гильдии без права manage roulette: показываем кнопку «Участвовать». */
const showParticipateBlock = computed(
  () => !props.model.canManageRoulette && props.model.isCurrentUserGuildMember
);

/** Тонкая ссылка на dot-цвет пользователя, если он состоит в группе подсветки. */
function memberDotColor(member: GuildRosterMember): string | null {
  return props.model.getMemberUserColor(member)?.dot ?? null;
}

function isMine(member: GuildRosterMember): boolean {
  return (
    member.user_id !== null &&
    props.model.currentUserId !== null &&
    member.user_id === props.model.currentUserId
  );
}

</script>

<template>
  <Card class="min-w-0 flex-1 lg:max-w-sm">
    <CardHeader>
      <!-- Сначала гости (не из состава), затем секция состава гильдии -->
      <div v-if="model.canManageRoulette" class="space-y-2">
        <Label for="wheel-external-nick" class="text-muted-foreground">
          Не из состава гильдии
        </Label>
        <div class="flex flex-wrap gap-2">
          <Input
            id="wheel-external-nick"
            v-model="model.externalWheelNickname"
            type="text"
            placeholder="Ник на колесо"
            class="min-w-0 flex-1"
            :disabled="!model.canEditWheelEntries"
            @keydown.enter.prevent="model.addExternalParticipantByNickname"
          />
          <Button
            type="button"
            size="sm"
            :disabled="!model.canEditWheelEntries"
            @click="model.addExternalParticipantByNickname"
          >
            На колесо
          </Button>
        </div>
        <p v-if="model.externalWheelHintError" class="text-xs text-destructive">
          {{ model.externalWheelHintError }}
        </p>
      </div>

      <CardTitle :class="model.canManageRoulette ? 'mt-6' : ''">
        Участники гильдии
      </CardTitle>
      <p
        v-if="!model.canManageRoulette"
        class="text-sm text-muted-foreground"
      >
        <template v-if="model.enrollmentOpen && model.isCurrentUserGuildMember">
          Идёт набор: нажмите «Участвовать», чтобы добавить своего персонажа на колесо.
        </template>
        <template v-else-if="model.isCurrentUserGuildMember">
          Просмотр состава. Когда офицер откроет набор — здесь появится кнопка
          «Участвовать».
        </template>
        <template v-else>
          Просмотр состава. Добавлять на колесо могут офицеры с правом «Управление
          рулеткой».
        </template>
      </p>

      <!-- Блок «Участвовать» для рядового пользователя гильдии -->
      <div
        v-if="showParticipateBlock"
        class="mt-3 flex flex-wrap items-center gap-2 rounded-md border border-emerald-600/30 bg-emerald-500/5 px-3 py-2 dark:border-emerald-400/30 dark:bg-emerald-500/10"
      >
        <p class="min-w-0 flex-1 text-xs text-emerald-800 dark:text-emerald-200">
          <template v-if="!model.enrollmentOpen">
            Набор участников закрыт.
          </template>
          <template v-else-if="model.myCharactersAvailable.length === 0">
            <template v-if="model.myCharactersOnWheel.length > 0">
              Все ваши персонажи уже на колесе.
            </template>
            <template v-else>
              Нет персонажей, доступных для добавления.
            </template>
          </template>
          <template v-else-if="model.myCharactersAvailable.length === 1">
            Доступен персонаж: «{{ model.myCharactersAvailable[0].name }}».
          </template>
          <template v-else>
            У вас несколько персонажей в гильдии — выберите, кого добавить
            (можно по одному).
          </template>
        </p>
        <Button
          type="button"
          size="sm"
          variant="default"
          class="shrink-0"
          :disabled="!model.canParticipateInRoulette"
          @click="model.openCharacterPicker"
        >
          Участвовать
        </Button>
      </div>

      <div class="mt-2 flex min-w-0 items-center gap-2">
        <Input
          v-model="model.searchQuery"
          type="search"
          placeholder="Поиск по имени…"
          class="min-w-0 flex-1"
        />
        <template v-if="model.canManageRoulette">
          <input
            ref="wheelExcelInputRef"
            type="file"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            class="sr-only"
            @change="onWheelExcelChange"
          />
          <Tooltip
            :content="wheelExcelImportHint"
            side="top"
            class="max-w-sm shrink-0 text-left"
          >
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="gap-1.5 shrink-0"
              aria-label="Загрузить список ников из Excel"
              :disabled="model.importWheelExcelLoading || !model.canEditWheelEntries"
              @click="openWheelExcelPicker"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="h-4 w-4 shrink-0"
                aria-hidden="true"
              >
                <path d="M4.226 20.925A2 2 0 0 0 6 22h12a2 2 0 0 0 2-2V8a2.4 2.4 0 0 0-.706-1.706l-3.588-3.588A2.4 2.4 0 0 0 14 2H6a2 2 0 0 0-2 2v3.127" />
                <path d="M14 2v5a1 1 0 0 0 1 1h5" />
                <path d="m5 11-3 3" />
                <path d="m5 17-3-3h10" />
              </svg>
              {{ model.importWheelExcelLoading ? 'Читаем…' : 'Excel' }}
            </Button>
          </Tooltip>
        </template>
      </div>
      <div v-if="model.canManageRoulette" class="mt-2 flex flex-wrap gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="
            model.roster.length === 0 ||
            model.allRosterAlreadyOnWheel ||
            !model.canEditWheelEntries
          "
          @click="model.addAllRosterToWheel"
        >
          Добавить всех
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="model.wheelEntries.length === 0 || !model.canEditWheelEntries"
          @click="model.resetWheelEntries"
        >
          Сбросить
        </Button>
      </div>
      <p
        v-if="model.canManageRoulette && model.importWheelExcelError"
        class="mt-2 text-xs text-destructive"
      >
        {{ model.importWheelExcelError }}
      </p>
    </CardHeader>
    <CardContent>
      <p v-if="model.loading" class="text-sm text-muted-foreground">Загрузка…</p>
      <p v-else-if="model.error" class="text-sm text-destructive">{{ model.error }}</p>
      <p
        v-else-if="model.filteredRoster.length === 0"
        class="text-sm text-muted-foreground"
      >
        {{
          model.searchQuery.trim() ? 'Никого не найдено.' : 'В гильдии пока никого нет.'
        }}
      </p>
      <ul v-else class="space-y-2">
        <li
          v-for="member in model.filteredRoster"
          :key="member.character_id"
          class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2"
        >
          <div class="flex min-w-0 flex-1 items-center gap-3">
            <span
              v-if="memberDotColor(member)"
              class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
              :style="{ backgroundColor: memberDotColor(member) ?? undefined }"
              aria-hidden="true"
              :title="
                isMine(member) ? 'Ваша группа персонажей' : 'Группа персонажей пользователя'
              "
            />
            <Avatar
              :src="member.avatar_url ?? undefined"
              :alt="member.name"
              :fallback="avatarFallback(member.name)"
              class="h-9 w-9 shrink-0"
            />
            <div class="min-w-0">
              <p class="truncate text-base font-medium">{{ member.name }}</p>
              <Badge
                v-if="member.guild_role"
                variant="secondary"
                class="mt-0.5 text-xs"
              >
                {{ member.guild_role.name }}
              </Badge>
            </div>
          </div>

          <!-- Менеджер: универсальная кнопка добавить/убрать -->
          <Button
            v-if="model.canManageRoulette && model.isInWheel(member.character_id)"
            variant="outline"
            size="sm"
            class="shrink-0"
            :disabled="!model.canEditWheelEntries"
            @click="model.removeGuildFromWheel(member.character_id)"
          >
            Убрать
          </Button>
          <Button
            v-else-if="model.canManageRoulette"
            variant="outlinePrimary"
            size="sm"
            class="shrink-0"
            :disabled="!model.canEditWheelEntries"
            @click="model.addToWheel(member)"
          >
            Добавить
          </Button>

          <!-- Рядовой пользователь: возможность убрать только своих персонажей -->
          <template v-else>
            <Button
              v-if="
                isMine(member) &&
                model.isInWheel(member.character_id) &&
                model.canRemoveOwnCharacter
              "
              variant="outline"
              size="sm"
              class="shrink-0"
              @click="model.removeOwnCharacterFromWheel(member.character_id)"
            >
              Убрать
            </Button>
            <span
              v-else-if="model.isInWheel(member.character_id)"
              class="shrink-0 text-xs text-muted-foreground"
            >
              На колесе
            </span>
          </template>
        </li>
      </ul>
    </CardContent>
  </Card>

  <CharacterPickerDialog
    :open="model.characterPickerOpen"
    :characters="model.myCharactersAvailable"
    @update:open="model.characterPickerOpen = $event"
    @pick="model.pickCharacterToParticipate"
  />
</template>
