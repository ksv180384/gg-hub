<script setup>
defineProps({
  skill: { type: Object, default: {} },
});
</script>

<template>
  <li class="card-skill">
    <img :src="skill.image"/>
    <div class="top-content">
      <div>
        <div>{{ skill.type_level }}</div>
        <div>{{ skill.weapon_type.name }}</div>
      </div>
      <div>
        <div>{{ skill.type }}</div>
        <div>{{ skill.level }}</div>
      </div>
    </div>
    <div class="header">
      {{ skill.name }}
    </div>
    <div class="info">
      <div v-for="info in skill.info" class="info-item">
        <div class="info-item-label">
          {{ info.name }}
        </div>
        <div class="info-item-content">
          {{ info.value }}
        </div>
      </div>
    </div>
    <div class="description" v-html="skill.description"></div>
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

.card-skill .info .info-item{
  @apply flex justify-between;
}

.card-skill .info .info-item-label{

}

.card-skill .info .info-item-content{

}

.card-skill .description{
  @apply flex-1;
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
