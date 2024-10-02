import { defineStore } from 'pinia';
import socket from '@/services/socketService.js';

export const useSocketStore = defineStore('socketStore', {
  state: () => ({
    socket: socket,
    is_connect: socket,
  }),
  actions: {
    setSocket(socket){
      this.socket = socket;
    },
  }
});
