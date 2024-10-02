<script setup>
import { onMounted, computed, ref } from 'vue';
import { useSocketStore } from '@/stores/socketStore.js';
import { useProcessesStore } from '@/stores/processesStore.js';

import Header from '@/Components/Head/Header.vue';

const socketStore = useSocketStore();
const processesStore = useProcessesStore();
const menu = ref(null);
const processes = computed(() => processesStore.processes);
const items = ref([
  {
    label: 'Refresh',
    icon: 'pi pi-refresh'
  },
  {
    label: 'Search',
    icon: 'pi pi-search'
  },
  {
    separator: true
  },
  {
    label: 'Delete',
    icon: 'pi pi-times'
  }
]);

const getPercent = (currentValue, totalValue) => {
  if(!currentValue || !totalValue){
    return 0
  }
  return (currentValue / totalValue) * 100;
};



const toggle = (event) => {
  menu.value.toggle(event);
};

const save = () => {
  toast.add({ severity: 'success', summary: 'Success', detail: 'Data Saved', life: 3000 });
};

onMounted(async () => {
  // socketStore.socket.io.opts.query = {
  //   user_id: currentUserId,
  //   chats: resChatsList?.map(item => item.id),
  // };

  await socketStore.socket.connect();
});
</script>

<template>
  <div class="">
    <Header/>
    <slot/>

    <div class="fixed bottom-1 right-1 w-[400px]">
      <template v-for="processKey in Object.keys(processes)">
        <Card v-if="processes?.[processKey]">
          <template #title>{{ processes?.[processKey].action_name }}</template>
          <template #content>
            <div>{{ processes[processKey].name }}</div>
            <small class="m-0">
              {{ processes[processKey].info }}
            </small>
            <template v-if="processes[processKey]?.progress">
              <ProgressBar :value="parseFloat(processes[processKey]?.progress?.toFixed(2))"/>
            </template>
          </template>
        </Card>
      </template>
    </div>
  </div>
</template>

<style scoped>

</style>
