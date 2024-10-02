<script setup>
import { ref } from 'vue';
import { translate } from '@/services/skillService.js';

import CardSkill from '@/Components/Skill/CardSkill.vue';

const props = defineProps({
  skill: { type: Object, default: {} },
});
const emits = defineEmits(['updateSkill']);

const controllList = ref();
const isSubmiting = ref(false);

const submitingTranslate = async () => {
  isSubmiting.value = true;
  try {
    const res = await translate({ ...props.skill, to_lang: 'ru' });
    emits('updateSkill', res);
  } catch (e) {
    console.error(e);
  } finally {
    isSubmiting.value = false;
  }
}

const toggle = (event) => {
  controllList.value.toggle(event);
}
</script>

<template>
  <div class="card-skill-parser">
    <CardSkill :skill="skill"/>
    <div class="card-skill-controll-block">
      <Button icon="pi pi-cog" class="!bg-white" type="button" severity="secondary" text raised size="small" @click="toggle" />
      <div>
        <Popover ref="controllList">
          <div class="flex flex-col gap-4 -m-4">
            <div>
              <ul class="list-none p-0 m-0 flex flex-col">
                <li
                  class="flex items-center gap-2 hover:bg-emphasis cursor-pointer rounded-border"
                  @click="submitingTranslate"
                >
                  <Button
                    label="Перевести на RU"
                    severity="secondary"
                    size="small"
                    text
                    :loading="isSubmitingTransferToSite"
                  />
                </li>
              </ul>
            </div>
          </div>
        </Popover>
      </div>
    </div>
  </div>
</template>

<style scoped>
.card-skill-parser{
  @apply relative w-[400px];
}

.card-skill-controll-block{
  @apply absolute bottom-4 right-2 flex justify-end w-full;
}
</style>

