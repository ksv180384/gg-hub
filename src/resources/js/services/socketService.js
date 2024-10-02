import { io } from 'socket.io-client';
import { useProcessesStore } from '@/stores/processesStore.js';
// import router from '@/router';
// import { useRoomsStore } from '@/store/chats_rooms.js';
// import { useUsersOnlineStore } from '@/store/users_online.js';
// import { usePageStore } from '@/store/page.js';

const connectionOptions = {
  forceNew: true,
  reconnectionAttempts: "Infinity",
  timeout: 10000,
  autoConnect: false,
  transports : ["websocket", "polling"],
};
const socket = io('localhost:3007', connectionOptions);

// Событие получения сообщения, получают все, кто состоит хоть в одном из чатов с отправителем
socket.on('action', function(data){
  // if(data.action === 'skill-parser-to-site'){
  //   console.log(data);
  // }
  const processesStore = useProcessesStore();
  processesStore.setProcess(data.action, data.data);
});

// Событие срабатывает при потере соединения с сервером
socket.on('disconnect', async () => {
  // store.commit('setIsSocketConnect', false);
  const usersOnlineStore =  useUsersOnlineStore();
  usersOnlineStore.clearData();
});

export default socket;
