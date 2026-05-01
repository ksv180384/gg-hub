<script setup lang="ts">
import { reactive } from 'vue';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Calendar,
} from '@/shared/ui';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import { useGuildCalendar } from '@/features/guild-calendar';
import NotFoundPage from '@/pages/not-found/index.vue';
import GuildCalendarSelectedDayCard from './GuildCalendarSelectedDayCard.vue';
import GuildEventFormDialog from './GuildEventFormDialog.vue';
import GuildEventViewDialog from './GuildEventViewDialog.vue';

const model = reactive(useGuildCalendar());
</script>

<template>
  <NotFoundPage v-if="model.calendarGuildAccessNotFound" />
  <div v-else class="container py-6">
    <div class="flex flex-col gap-6 lg:flex-row">
      <Card class="flex-1">
        <CardHeader>
          <CardTitle>Календарь событий</CardTitle>
        </CardHeader>
        <CardContent>
          <Calendar
            :events="model.calendarEvents"
            :selected-date="model.selectedDate"
            :show-new-event-button="model.canAddEvent"
            @view-range="model.onViewRange"
            @select-date="model.handleSelectDate"
            @new-event="model.openCreateModal"
            @click-event="model.handleClickEvent"
          />
        </CardContent>
      </Card>

      <GuildCalendarSelectedDayCard
        v-if="model.selectedDate"
        :selected-date-label="model.selectedDateLabel"
        :events="model.eventsForSelectedDay"
        :can-add-event="model.canAddEvent"
        :can-delete-event="model.canDeleteEvent"
        @create="model.openCreateModal"
        @open="model.openEvent"
        @delete="model.askDelete"
      />
    </div>

    <GuildEventFormDialog
      v-model:open="model.modalOpen"
      v-model:form="model.form"
      :modal-editing-id="model.modalEditingId"
      :form-error="model.formError"
      :form-loading="model.formLoading"
      :loading-my-characters="model.loadingMyCharacters"
      :my-characters-in-guild="model.myCharactersInGuild"
      :can-delete-event="model.canDeleteEvent"
      :show-discord-notification-toggle="model.guildDiscordEventStartingEnabled"
      @submit="model.submitForm"
      @cancel="model.closeModal"
      @delete-from-edit="model.deleteFromEditForm"
    />

    <GuildEventViewDialog
      v-model:open="model.viewModalOpen"
      :view-event="model.viewEvent"
    />

    <ConfirmDialog
      v-model:open="model.deleteConfirmOpen"
      title="Удалить событие?"
      description="Событие будет удалено без возможности восстановления."
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="model.deleteLoading"
      confirm-variant="destructive"
      @confirm="model.confirmDelete"
    />
  </div>
</template>
