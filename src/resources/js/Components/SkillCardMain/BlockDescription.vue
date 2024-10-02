<script setup>
import { computed } from 'vue';

import ItemElement from '@/Components/SkillCardMain/ItemElement.vue';

const props = defineProps({
  description: { type: Object, default: {} },
});

const arString = computed(() => {
  if(!props.description.content_ru){
    return [];
  }
  const separators = /\[\[|\]\]/; // Регулярное выражение для разделителей
  const arDescription = props.description.content_ru.split(separators).map(part => part.trim()).filter(part => part);
  const items = [...props.description.items];
  const arElements = [];
  for (const descriptionKey in arDescription){
    let isComponent = false;
    for (const item in items){
      if(arDescription[descriptionKey] === items[item].text_original){
        arElements.push({ type: 'component', description: '', data: items[item] });
        isComponent = true;
        break;
      }
    }
    if(!isComponent){
      arElements.push({ type: 'text', description: arDescription[descriptionKey], data: {} });
    }
  }
  return arElements;
});

// ЭТО ОПТИМИЗИРОВАННЫЙ ВАРИАНТ !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// const arString = computed(() => {
//   const separators = /\[\[|\]\]/; // Регулярное выражение для разделителей
//   const arDescription = props.description.content_ru
//     .split(separators)
//     .map(part => part.trim())
//     .filter(Boolean);
//
//   const itemsMap = new Map(props.description.items.map(item => [item.text_original, item]));
//
//   return arDescription.map(part => {
//     const item = itemsMap.get(part);
//     if (item) {
//       return { type: 'component', description: '', data: item };
//     }
//     return { type: 'text', description: part, data: {} };
//   });
// });
</script>

<template>
  <div class="description">
    <template v-for="element in arString">
      <component v-if="element.type === 'component'"
                 :is="ItemElement"
                 :text-original="element.data.text_original"
                 :text-ru="element.data.text_ru" />
      <span v-else>{{ element.description }}</span>
    </template>
    <hr>

  </div>
</template>

<style scoped>
.description{
  @apply flex-1;
}


</style>
