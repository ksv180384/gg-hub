<script setup>

import BlockTypeLevel from '@/Components/SkillCardMain/BlockTypeLevel.vue';
import BlockWeapon from '@/Components/SkillCardMain/BlockWeapon.vue';
import BlockType from '@/Components/SkillCardMain/BlockType.vue';
import BlockLevel from '@/Components/SkillCardMain/BlockLevel.vue';
import BlockName from '@/Components/SkillCardMain/BlockName.vue';
import BlockInfoItem from '@/Components/SkillCardMain/BlockInfoItem.vue';
import BlockDescription from '@/Components/SkillCardMain/BlockDescription.vue';

defineProps({
  skill: { type: Object, default: {} },
});
</script>

<template>
  <li class="card-skill">
    <img :src="skill.image"/>
    <div class="top-content">
      <div>
        <BlockTypeLevel
          :type-level="skill.type_level || {}"
        />
        <BlockWeapon
          :weapon="skill.weapon_type || {}"
        />
      </div>
      <div>
        <BlockType :type="skill.type || {}"/>
        <BlockLevel :level="skill.level || {}"/>
      </div>
    </div>
    <div class="header">
      <BlockName :name="skill.name "/>
    </div>
    <div class="info">
      <template v-for="info in skill.info">
        <BlockInfoItem :info="info"/>
      </template>
    </div>
    <BlockDescription :description="skill.description || {}"/>
    <div class="update-info">
      {{ skill.unlocked_lvl_text }}
    </div>
    <div class="update-lvl">
      <div v-for="updateItem in skill.update_lvl" class="update-lvl-item">
        <div>
          {{ updateItem.name }}
        </div>
        <div>
          {{ updateItem.value }}
        </div>
      </div>
    </div>
    <div class="unlocked-lvl">
      {{ skill.unlocked_lvl }}
    </div>
    <div class="footer">

    </div>
  </li>
</template>

<style scoped>
.card-skill{
  @apply flex flex-col gap-3 my-2 bg-gray-50 relative overflow-visible p-4 rounded-lg
  w-[400px] mt-9 shadow-lg;
}

.card-skill::after{
  content: ' ';
  z-index: 0;
  @apply rounded w-[60px] h-[60px] absolute left-1/2 top-0 -translate-x-1/2
  -translate-y-1/2 shadow-md;
}

.card-skill img{
  z-index: 1;
  @apply rounded w-[60px] h-[60px] object-cover absolute left-1/2 top-0 -translate-x-1/2
  -translate-y-1/2;
}
.card-skill .top-content{
  @apply flex justify-between;
}

.card-skill .header{
  @apply text-lg font-bold text-center;
}

.card-skill .info{
  @apply flex-1 flex flex-col gap-1;
}

.card-skill .update-info{

}

.card-skill .update-lvl{
  @apply flex flex-col gap-3;
}

.card-skill .update-lvl-item{

}

.card-skill .unlocked-lvl{

}

.card-skill .footer{

}
</style>
