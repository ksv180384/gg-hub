<script setup lang="ts">
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, Tooltip } from '@/shared/ui';
import { SpinWheel } from '@/widgets/spin-wheel';
import {
  WHEEL_EMPTY_PLACEHOLDER,
  type GuildAuctionRouletteModel,
  type WheelEntry,
} from '@/features/guild-auction-roulette';
import type { GuildAuctionSpinWheelExpose } from '@/shared/lib/useGuildAuctionWheelSocket';
import EnrollmentToggleButton from './EnrollmentToggleButton.vue';
import RouletteWinnerBanner from './RouletteWinnerBanner.vue';
import SocketStatusMessage from './SocketStatusMessage.vue';
import RouletteSettingsDialog from './RouletteSettingsDialog.vue';
import WheelDurationField from './WheelDurationField.vue';
import WheelEntriesList from './WheelEntriesList.vue';

const props = defineProps<{
  model: GuildAuctionRouletteModel;
}>();

const settingsOpen = ref(false);

function resolveUserColor(entry: WheelEntry) {
  return props.model.getEntryUserColor(entry);
}

/**
 * Резолвер «можно убрать запись» для рядового члена гильдии: он удаляет только
 * своих персонажей, и только пока набор открыт и нет вращения.
 */
function resolveCanRemove(entry: WheelEntry): boolean {
  if (entry.kind !== 'guild') return false;
  if (!props.model.canRemoveOwnCharacter) return false;
  return props.model.myCharactersOnWheel.some(
    (m) => m.character_id === entry.character_id
  );
}

/**
 * ✕ в списке «На колесе»: менеджер — полное обновление через сокет;
 * рядовой участник — только свои персонажи через `auction:entries:remove`.
 */
function onRemoveGuildFromWheelList(characterId: number) {
  if (props.model.canManageRoulette) {
    props.model.removeGuildFromWheel(characterId);
    return;
  }
  props.model.removeOwnCharacterFromWheel(characterId);
}

/** Function ref: пробрасываем экземпляр SpinWheel в state модели через сеттер. */
function setSpinWheelRef(el: unknown) {
  props.model.setSpinWheelInstance(
    (el as GuildAuctionSpinWheelExpose | null) ?? null
  );
}

const wheelCardInfoHint =
  'Добавьте участников справа или загрузите список из Excel, затем крутите колесо.\n\nРулетка и список на колесе синхронизируются у всех, кто открыл эту страницу.\n\nИзменять состав колеса и запускать розыгрыш могут только участники с правом «Управление рулеткой» (роли гильдии).\n\nДлительность вращения (секунды): чем больше значение, тем плавнее колесо замедляется перед остановкой.';

/** Диаметр колеса (px), совпадает с `:size` у `SpinWheel`. */
const SPIN_WHEEL_SIZE_PX = 360;
/**
 * Высота зоны стрелки над диском в `SpinWheel.vue` (`POINTER_HEIGHT`).
 * При смене там — обновить и здесь.
 */
const SPIN_WHEEL_POINTER_PX = 20;
/** Центр диска по вертикали от верха корня `SpinWheel` (верх canvas). */
const WINNER_OVERLAY_CENTER_TOP_PX =
  SPIN_WHEEL_POINTER_PX + SPIN_WHEEL_SIZE_PX / 2;
</script>

<template>
  <div class="min-w-0 w-full max-w-full shrink-0 lg:w-auto">
    <Card class="min-w-0 max-w-full overflow-hidden">
      <CardHeader class="space-y-3">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
          <CardTitle class="flex shrink-0 items-center gap-1.5 text-left">
            Рулетка гильдии
            <Tooltip
              :content="wheelCardInfoHint"
              side="top"
              class="max-w-[min(100vw-2rem,22rem)] whitespace-pre-line text-left"
            >
              <button
                type="button"
                class="inline-flex shrink-0 rounded-sm text-muted-foreground outline-none ring-offset-background transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                aria-label="Справка по рулетке гильдии"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="h-4 w-4"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="10" />
                  <path d="M12 16v-4" />
                  <path d="M12 8h.01" />
                </svg>
              </button>
            </Tooltip>
          </CardTitle>
          <div class="flex min-w-0 flex-wrap items-center justify-center gap-2 sm:ml-auto sm:justify-end">
            <EnrollmentToggleButton
              v-if="model.canManageRoulette"
              :enrollment-open="model.enrollmentOpen"
              :disabled="model.isWheelSpinning"
              :remote-available="model.remoteSpin"
              @open="model.openEnrollment"
              @close="model.closeEnrollment"
            />
          </div>
        </div>
      </CardHeader>
      <CardContent class="flex flex-col items-stretch gap-6">
        <SocketStatusMessage
          :socket-configured="model.socketConfigured"
          :socket-connected="model.socketConnected"
          :socket-connect-error="model.socketConnectError"
          :socket-uses-explicit-url="model.socketUsesExplicitUrl"
          :remote-spin="model.remoteSpin"
        />
        <WheelDurationField
          v-if="model.canManageRoulette"
          v-model="model.wheelSpinDurationSeconds"
          :countdown-seconds="model.wheelSpinCountdownSeconds"
          :disabled="model.isWheelSpinning"
          @blur="model.clampWheelSpinSecondsField"
        >
          <template #right>
            <Tooltip content="Настройки рулетки" side="top">
              <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-background text-foreground shadow-sm transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                aria-label="Настройки рулетки"
                @click="settingsOpen = true"
              >
                <!-- Lucide settings -->
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
                  <path
                    d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"
                  />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              </button>
            </Tooltip>
          </template>
        </WheelDurationField>
        <RouletteSettingsDialog
          v-if="model.canManageRoulette"
          v-model:open="settingsOpen"
          :model="model"
        />
        <div class="flex justify-center">
          <div class="relative inline-flex">
            <SpinWheel
              :ref="setSpinWheelRef"
              :options="model.wheelOptions.length > 0 ? model.wheelOptions : [WHEEL_EMPTY_PLACEHOLDER]"
              :size="SPIN_WHEEL_SIZE_PX"
              :duration="model.wheelSpinDurationMs"
              :remote-spin="model.remoteSpin"
              :show-spin-button="model.canManageRoulette"
              :spin-disabled="model.wheelEntries.length === 0"
              :hide-inline-countdown="model.canManageRoulette"
              @result="model.onSpinWheelResult"
              @spin-start="model.onSpinWheelStart"
              @spin-request="model.requestSpin"
            />
            <span
              v-if="model.enrollmentOpen"
              class="pointer-events-none absolute inline-flex items-center gap-1 rounded-full border border-emerald-600/40 bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-800 backdrop-blur-sm dark:border-emerald-400/40 dark:bg-emerald-500/15 dark:text-emerald-100"
              :style="{ top: '-10px', right: '-10px' }"
            >
              <span
                class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"
                aria-hidden="true"
              />
              Набор открыт
            </span>
            <RouletteWinnerBanner
              :show="model.showWheelWinnerBanner"
              :display-key="model.winnerDisplayKey"
              :winner-name="model.wheelSpinResult"
              :wheel-center-top-px="WINNER_OVERLAY_CENTER_TOP_PX"
            />
          </div>
        </div>
        <WheelEntriesList
          :entries="model.wheelEntries"
          :options="model.wheelOptions"
          :can-edit-wheel-entries="model.canEditWheelEntries"
          :resolve-can-remove="resolveCanRemove"
          :resolve-user-color="resolveUserColor"
          @remove-guild="onRemoveGuildFromWheelList"
          @remove-external="model.removeExternalFromWheel"
        />
      </CardContent>
    </Card>
  </div>
</template>
