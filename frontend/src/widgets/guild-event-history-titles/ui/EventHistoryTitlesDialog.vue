<script setup lang="ts">
import { Button, Input, Label, Tooltip } from '@/shared/ui';
import type { EventHistoryTitleDto } from '@/shared/api/eventHistoryTitlesApi';
import type { EventHistoryTitleForm } from '@/features/guild-event-history-titles';

const DISTRIBUTE_DKP_TOOLTIP =
  'Очки ДКП будут добавляться во время создания / редактирования события и будут распределены между участниками события с учётом коэффициентов участников.';

function onDistributeToggle(checked: boolean, target: EventHistoryTitleForm) {
  if (checked) {
    target.dkp_base_points = '';
  }
}

defineProps<{
  loading: boolean;
  listError: string;
  formError: string;
  saving: boolean;
  deletingId: number | null;
  sortedTitles: EventHistoryTitleDto[];
  editingId: number | null;
  createFormOpen: boolean;
  dkpEnabled: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const form = defineModel<EventHistoryTitleForm>('form', { required: true });
const editForm = defineModel<EventHistoryTitleForm>('editForm', { required: true });

const emit = defineEmits<{
  openCreate: [];
  cancelCreate: [];
  create: [];
  startEdit: [title: EventHistoryTitleDto];
  saveEdit: [];
  cancelEdit: [];
  delete: [title: EventHistoryTitleDto];
}>();
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="flex max-h-[min(90vh,40rem)] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-lg">
      <div class="border-b border-border px-4 py-3">
        <div class="text-base font-semibold">Виды событий</div>
        <p class="mt-1 text-xs text-muted-foreground">
          Справочник названий для истории событий гильдии. Удалить вид можно только если по нему ещё нет записей в истории.
        </p>
      </div>

      <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4">
        <div v-if="createFormOpen" class="space-y-3 rounded-lg border border-border p-3">
          <div
            class="grid grid-cols-1 gap-3"
            :class="dkpEnabled ? 'sm:grid-cols-2 sm:items-end' : ''"
          >
            <div class="space-y-2">
              <Label for="event-title-name">Название *</Label>
              <Input
                id="event-title-name"
                v-model="form.name"
                type="text"
                maxlength="255"
                class="h-9"
                :disabled="saving"
                required
              />
            </div>
            <div v-if="dkpEnabled" class="space-y-2">
              <Label for="event-title-dkp">Очки ДКП</Label>
              <Input
                id="event-title-dkp"
                v-model="form.dkp_base_points"
                type="number"
                min="0"
                step="1"
                class="h-9"
                :disabled="saving || form.distribute_dkp_to_participants"
                placeholder="Не задано"
              />
            </div>
          </div>
          <div
            v-if="dkpEnabled"
            class="flex items-start gap-2 rounded-md border border-border/60 p-2"
          >
            <input
              id="event-title-distribute-dkp"
              v-model="form.distribute_dkp_to_participants"
              type="checkbox"
              class="mt-0.5 h-4 w-4 shrink-0 rounded border-input"
              :disabled="saving"
              @change="onDistributeToggle(form.distribute_dkp_to_participants, form)"
            >
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
              <Label for="event-title-distribute-dkp" class="font-normal leading-snug">
                Распределять очки по участникам события
              </Label>
              <Tooltip :content="DISTRIBUTE_DKP_TOOLTIP" side="top" class="max-w-sm text-left">
                <button
                  type="button"
                  class="inline-flex size-5 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                  aria-label="Подсказка о распределении очков ДКП"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 16v-4" />
                    <path d="M12 8h.01" />
                  </svg>
                </button>
              </Tooltip>
            </div>
          </div>

          <div class="flex flex-wrap justify-end gap-2">
            <Button variant="outline" :disabled="saving" @click="emit('cancelCreate')">Отмена</Button>
            <Button :disabled="saving" @click="emit('create')">
              {{ saving ? 'Добавление…' : 'Добавить' }}
            </Button>
          </div>
          <p v-if="formError && createFormOpen && editingId == null" class="text-sm text-destructive">{{ formError }}</p>
        </div>

        <div class="space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium">Список видов</p>
            <Button
              v-if="!createFormOpen"
              type="button"
              size="sm"
              :disabled="saving"
              @click="emit('openCreate')"
            >
              Добавить
            </Button>
          </div>
          <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
          <p v-else-if="listError" class="text-sm text-destructive">{{ listError }}</p>
          <p v-else-if="!sortedTitles.length" class="text-sm text-muted-foreground">Пока нет видов событий.</p>
          <ul v-else class="space-y-2">
            <li
              v-for="title in sortedTitles"
              :key="title.id"
              class="rounded-lg border border-border px-3 py-3"
            >
              <template v-if="editingId === title.id">
                <div
                  class="grid grid-cols-1 gap-3"
                  :class="dkpEnabled ? 'sm:grid-cols-2 sm:items-end' : ''"
                >
                  <div class="space-y-2">
                    <Label :for="`event-title-edit-name-${title.id}`">Название *</Label>
                    <Input
                      :id="`event-title-edit-name-${title.id}`"
                      v-model="editForm.name"
                      type="text"
                      maxlength="255"
                      class="h-9"
                      :disabled="saving"
                      required
                    />
                  </div>
                  <div v-if="dkpEnabled" class="space-y-2">
                    <Label :for="`event-title-edit-dkp-${title.id}`">Очки ДКП</Label>
                    <Input
                      :id="`event-title-edit-dkp-${title.id}`"
                      v-model="editForm.dkp_base_points"
                      type="number"
                      min="0"
                      step="1"
                      class="h-9"
                      :disabled="saving || editForm.distribute_dkp_to_participants"
                      placeholder="Не задано"
                    />
                  </div>
                </div>
          <div
            v-if="dkpEnabled"
            class="flex items-start gap-2 rounded-md border border-border/60 p-2"
          >
            <input
              :id="`event-title-edit-distribute-dkp-${title.id}`"
              v-model="editForm.distribute_dkp_to_participants"
              type="checkbox"
              class="mt-0.5 h-4 w-4 shrink-0 rounded border-input"
              :disabled="saving"
              @change="onDistributeToggle(editForm.distribute_dkp_to_participants, editForm)"
            >
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
              <Label :for="`event-title-edit-distribute-dkp-${title.id}`" class="font-normal leading-snug">
                Распределять очки по участникам события
              </Label>
              <Tooltip :content="DISTRIBUTE_DKP_TOOLTIP" side="top" class="max-w-sm text-left">
                <button
                  type="button"
                  class="inline-flex size-5 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                  aria-label="Подсказка о распределении очков ДКП"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 16v-4" />
                    <path d="M12 8h.01" />
                  </svg>
                </button>
              </Tooltip>
            </div>
          </div>

                <div class="mt-3 flex flex-wrap justify-end gap-2">
                  <Button variant="outline" :disabled="saving" @click="emit('cancelEdit')">Отмена</Button>
                  <Button :disabled="saving" @click="emit('saveEdit')">
                    {{ saving ? 'Сохранение…' : 'Сохранить' }}
                  </Button>
                </div>
                <p v-if="formError" class="mt-2 text-sm text-destructive">{{ formError }}</p>
              </template>
              <template v-else>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-medium">{{ title.name }}</div>
                    <div class="text-xs text-muted-foreground">
                      <template v-if="dkpEnabled">
                        <template v-if="title.distribute_dkp_to_participants">
                          ДКП: распределение по участникам
                        </template>
                        <template v-else>
                          ДКП: {{ title.dkp_base_points ?? '—' }}
                        </template>
                        <span> · </span>
                      </template>
                      Записей в истории: {{ title.histories_count ?? 0 }}
                    </div>
                  </div>
                  <div class="flex shrink-0 flex-wrap gap-1">
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      class="h-8 w-8 text-muted-foreground hover:text-foreground"
                      aria-label="Изменить"
                      title="Изменить"
                      @click="emit('startEdit', title)"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z" />
                      </svg>
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      class="h-8 w-8 text-muted-foreground hover:text-destructive"
                      :disabled="(title.histories_count ?? 0) > 0 || deletingId === title.id"
                      :aria-label="deletingId === title.id ? 'Удаление…' : 'Удалить'"
                      :title="
                        deletingId === title.id
                          ? 'Удаление…'
                          : (title.histories_count ?? 0) > 0
                            ? 'Нельзя удалить: вид уже используется в истории событий.'
                            : 'Удалить'
                      "
                      @click="emit('delete', title)"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path d="M3 6h18" />
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                      </svg>
                    </Button>
                  </div>
                </div>
              </template>
            </li>
          </ul>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
        <Button variant="outline" :disabled="saving || deletingId != null" @click="open = false">
          Закрыть
        </Button>
      </div>
    </div>
  </div>
</template>
