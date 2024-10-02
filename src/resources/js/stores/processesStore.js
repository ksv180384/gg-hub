import { defineStore } from 'pinia';
import { getSkillsAllStorage } from '@/services/skillService.js';

export const useProcessesStore = defineStore('processes', {
  state: () => ({
    processes: {},
    timeOutCloseId: null,
  }),
  actions: {
    setProcess(processName, data) {
      if(data.status && data.status === 'end'){
        this.processes[processName] = null;
        return;
      }
      if(!data.status){
        clearTimeout(this.timeOutCloseId)
        this.timeOutCloseId = setTimeout(async () => {
          this.processes = {};
        }, 5000);
      }
      this.processes[processName] = data;
    },
  },
});
