<script setup lang="ts">
import { reactive } from 'vue';
import { Card } from '@/shared/ui';
import { useGuildSettingsModel } from '@/features/guild-settings';
import SidebarCard from './SidebarCard.vue';
import TabsHeader from './TabsHeader.vue';
import SettingsTab from './tabs/SettingsTab.vue';
import AboutTab from './tabs/AboutTab.vue';
import CharterTab from './tabs/CharterTab.vue';
import ApplicationTab from './tabs/ApplicationTab.vue';
import DiscordTab from './tabs/DiscordTab.vue';
import ApplicationFieldModal from './modals/ApplicationFieldModal.vue';
import TagDeleteConfirm from './modals/TagDeleteConfirm.vue';
import LeaveGuildConfirm from './modals/LeaveGuildConfirm.vue';
import LeaderChangeConfirm from './modals/LeaderChangeConfirm.vue';

// Важно: template не разворачивает refs по цепочке `model.foo`.
// `reactive()` даёт ref-unwrapping для свойств объекта, чтобы вниз не прокидывались Ref<...>.
const model = reactive(useGuildSettingsModel());
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <div v-if="model.error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ model.error }}
      </div>

      <div v-if="model.loading" class="text-muted-foreground">Загрузка…</div>

      <template v-else-if="model.guild">
        <div class="flex flex-col gap-6 md:flex-row md:items-start">
          <SidebarCard
            :guild="model.guild"
            :is-owner="model.isOwner"
            :can-edit-guild-data="model.canEditGuildData"
            :saving="model.saving"
            :accept-images="model.ACCEPT_IMAGES"
            :file-input-ref="model.fileInputRef"
            :drag-over="model.dragOver"
            :logo-display-url="model.logoDisplayUrl"
            :selected-tag-ids="model.selectedTagIds"
            :all-tags="model.allTags"
            :can-change-guild-leader="model.canChangeGuildLeader"
            :leader-roster-members="model.leaderRosterMembers"
            :selected-leader-character-id="model.selectedLeaderCharacterId"
            :field-errors="model.fieldErrors"
            :leaving="model.leaving"
            :leave-error="model.leaveError"
            @logoChange="model.onLogoChange"
            @openFilePicker="model.openFilePicker"
            @logoDragOver="model.onLogoDragOver"
            @logoDragLeave="model.onLogoDragLeave"
            @logoDrop="model.onLogoDrop"
            @removeLogo="model.removeLogoAndSave"
            @update:selectedLeaderCharacterId="model.selectedLeaderCharacterId = $event"
            @openLeave="model.leaveDialogOpen = true"
          />

          <div class="min-w-0 flex-1 order-2 md:order-2">
            <TabsHeader
              :tabs="model.visibleTabs"
              :active-tab="model.activeTab"
              @update:activeTab="model.activeTab = $event"
            />

            <SettingsTab
              v-show="model.activeTab === 'settings'"
              :is-owner="model.isOwner"
              :can-edit-guild-data="model.canEditGuildData"
              :can-edit-guild-tags="model.canEditGuildTags"
              :can-open-guild-tag-picker="model.canOpenGuildTagPicker"
              :can-create-guild-tag="model.canCreateGuildTag"
              :can-delete-guild-tag="model.canDeleteGuildTag"
              :can-change-guild-leader="model.canChangeGuildLeader"
              :leader-roster-members="model.leaderRosterMembers"
              :selected-leader-character-id="model.selectedLeaderCharacterId"
              :name="model.name"
              :selected-localization-id="model.selectedLocalizationId"
              :selected-server-id="model.selectedServerId"
              :can-change-localization-server="model.canChangeLocalizationServer"
              :available-localizations="model.availableLocalizations"
              :servers="model.servers"
              :show-roster-to-all="model.showRosterToAll"
              :all-tags="model.allTags"
              :selected-tag-ids="model.selectedTagIds"
              :guild-id="model.guildId"
              :saving="model.saving"
              :field-errors="model.fieldErrors"
              :localization-server-info="model.LOCALIZATION_SERVER_INFO"
              @update:name="model.name = $event"
              @update:selectedLocalizationId="model.selectedLocalizationId = $event"
              @update:selectedServerId="model.selectedServerId = $event"
              @update:showRosterToAll="model.showRosterToAll = $event"
              @update:selectedLeaderCharacterId="model.selectedLeaderCharacterId = $event"
              @save="model.saveSettings"
              @toggleTag="model.toggleTag"
              @update:allTags="model.allTags = $event"
              @update:selectedTagIds="model.selectedTagIds = $event"
              @deleteTag="model.openTagDeleteConfirm"
            />

            <AboutTab
              v-show="model.activeTab === 'about'"
              :can-edit="model.canEditAbout"
              :is-owner="model.isOwner"
              :saving="model.saving"
              :model-value="model.aboutText"
              :preview-mode="model.aboutPreviewMode"
              :read-only-html="model.guild?.about_text ?? null"
              @update:modelValue="model.aboutText = $event"
              @update:previewMode="model.aboutPreviewMode = $event"
              @save="model.saveAbout"
            />

            <CharterTab
              v-show="model.activeTab === 'charter'"
              :can-edit="model.canEditCharter"
              :is-owner="model.isOwner"
              :saving="model.saving"
              :model-value="model.charterText"
              :preview-mode="model.charterPreviewMode"
              :read-only-text="model.guild?.charter_text ?? null"
              @update:modelValue="model.charterText = $event"
              @update:previewMode="model.charterPreviewMode = $event"
              @save="model.saveCharter"
            />

            <ApplicationTab
              v-show="model.activeTab === 'application'"
              :application-short-url="model.applicationShortUrl"
              :guild-page-short-url="model.guildPageShortUrl"
              :fields="model.applicationFormFields"
              :saving="model.applicationFieldSaving"
              :is-recruiting="model.isRecruiting"
              :toggling-recruiting="model.togglingRecruiting"
              :is-select-or-multiselect="model.isSelectOrMultiselect"
              @add="model.openAddApplicationFieldModal"
              @edit="model.openEditApplicationFieldModal"
              @delete="model.deleteApplicationField"
              @toggleRecruiting="model.toggleRecruiting"
            />

            <DiscordTab
              v-show="model.activeTab === 'discord'"
              :webhook-url="model.discordWebhookUrl"
              :webhook-error="model.discordWebhookError"
              :notifications="model.discordNotifications"
              :saving="model.discordSaving"
              :saving-notification-key="model.discordNotifySavingKey"
              @update:webhookUrl="model.discordWebhookUrl = $event"
              @notification-change="model.saveDiscordNotification"
              @save="model.saveDiscord"
            />
          </div>
        </div>

        <ApplicationFieldModal
          :open="model.applicationFieldModalOpen"
          :saving="model.applicationFieldSaving"
          :edit-index="model.applicationFieldEditIndex"
          :name="model.applicationFieldName"
          :type="model.applicationFieldType"
          :required="model.applicationFieldRequired"
          :options="model.applicationFieldOptions"
          :type-options="model.APPLICATION_FIELD_TYPE_OPTIONS"
          :is-select-or-multiselect="model.isSelectOrMultiselect"
          @update:open="(v) => { model.applicationFieldModalOpen = v; if (!v) model.applicationFieldEditIndex = null; }"
          @update:name="model.applicationFieldName = $event"
          @update:type="model.applicationFieldType = $event"
          @update:required="model.applicationFieldRequired = $event"
          @add-option="model.addApplicationFieldOption"
          @remove-option="model.removeApplicationFieldOption"
          @set-option="model.setApplicationFieldOptionValue($event.index, $event.value)"
          @cancel="model.closeApplicationFieldModal"
          @save="model.saveApplicationFieldModal"
        />
      </template>

      <TagDeleteConfirm
        :open="model.tagDeleteDialogOpen"
        :tag="model.tagToDelete"
        :loading="model.tagDeleteLoading"
        @update:open="(v) => { model.tagDeleteDialogOpen = v; }"
        @confirm="model.confirmDeleteTagForever"
      />

      <LeaveGuildConfirm
        :open="model.leaveDialogOpen"
        :loading="model.leaving"
        @update:open="(v) => { model.leaveDialogOpen = v; }"
        @confirm="model.confirmLeaveGuild"
      />

      <LeaderChangeConfirm
        :open="model.leaderChangeDialogOpen"
        :new-leader-name="model.selectedNewLeaderName"
        :loading="model.saving"
        @update:open="(v) => { model.leaderChangeDialogOpen = v; }"
        @confirm="model.confirmLeaderChange"
      />
    </div>
  </div>
</template>

