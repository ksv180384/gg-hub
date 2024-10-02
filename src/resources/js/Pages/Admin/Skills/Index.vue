<script setup>
import { ref, onMounted } from 'vue';
import { getInfoAllSkills } from '@/services/parserService.js';
import { transferToSiteAll } from '@/services/skillService.js';

import AdmLayout from '@/Layouts/AdmLayout.vue';
import CardSkillParser from '@/Components/Skill/CardSkillParser.vue';
import CardSkillEn from '@/Components/Skill/CardSkillEn.vue';
import CardSkillMain from '@/Components/SkillCardMain/CardSkillMain.vue';

const props = defineProps({
  skills: Array,
});

const menu = ref();
const skillsList = ref(props.skills);
const isLoadingSkills = ref(false);
const isControlPanelItem = ref(false);

const actionTransferToSiteAll = async () => {
  isControlPanelItem.value = true;
  try {
    await transferToSiteAll();
  } catch (e) {
    console.error(e);
  } finally {
    isControlPanelItem.value = false;
  }
  console.log('transferToSiteAll');
}

const actionTranslateAll = () => {
  console.log('translateAll');
}

const controlPanelItems = ref([
  { label: 'Перенести все на сайт', method: actionTransferToSiteAll },
  { label: 'Перевести все', method: actionTranslateAll },
]);

const onUpdateSkill = (skills) => {
  skillsList.value = skills;
}
const toggle = (event) => {
  menu.value.toggle(event);
};
</script>

<template>
  <AdmLayout>
    <div class="top-control-panel">
      <ul>
        <li></li>
      </ul>
    </div>
    <div class="card flex justify-between">
      <div>

      </div>
      <div>
        <Button
          icon="pi pi-ellipsis-v"
          size="small"
          type="button"
          aria-haspopup="true"
          aria-controls="overlay_menu"
          :loading="isControlPanelItem"
          @click="toggle"
        />
        <Menu ref="menu" id="overlay_menu" :model="controlPanelItems" :popup="true">
          <template #item="{ item, props }">
            <a v-ripple class="flex items-center" @click="item.method" v-bind="props.action">
              <span>{{ item.label }}</span>
            </a>
          </template>
        </Menu>
      </div>
    </div>
<!--    <Menubar :model="controlPanelItems" :popup="true" >-->
<!--      <template #item="{ item, props, hasSubmenu }">-->
<!--        <Button label="Submit" size="small">{{ item.label }}</Button>-->
<!--      </template>-->
<!--    </Menubar>-->

    <ul class="px-4">
      <template v-for="skill in skillsList">
        <div class="flex flex-row gap-6">
          <CardSkillParser
            :skill="skill.parser_info"
            @updateSkill="onUpdateSkill"
          />
          <CardSkillEn
            v-if="skill.site_info_original"
            :skill="skill.site_info_original"
            @updateSkill="onUpdateSkill"
          />
          <CardSkillMain
            v-if="skill.site_info"
            :skill="skill.site_info"
          />
        </div>
      </template>
    </ul>
  </AdmLayout>
</template>

<style scoped>
.top-control-panel{

}
</style>
