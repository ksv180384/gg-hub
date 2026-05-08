<script setup lang="ts">
defineProps<{
  socketConfigured: boolean;
  socketConnected: boolean;
  socketConnectError: string | null;
  socketUsesExplicitUrl: boolean;
  remoteSpin: boolean;
}>();
</script>

<template>
  <p v-if="!socketConfigured" class="text-center text-xs text-muted-foreground">
    Синхронизация отключена
    (<code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">VITE_SOCKET_URL=off</code>).
  </p>
  <p v-else-if="socketConnectError" class="text-center text-xs text-destructive">
    Не удалось подключиться к синхронизации: {{ socketConnectError }}.
    <template v-if="socketUsesExplicitUrl">
      Проверьте
      <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">VITE_SOCKET_URL</code>
      — адрес должен открываться из браузера (не имя сервиса Docker вроде
      <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">socket_server</code>).
    </template>
    <template v-else>
      Запущен ли контейнер сокет-сервера и прокси
      <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">/socket.io</code>
      в nginx (или dev-прокси Vite)? Перезапустите
      <code class="rounded bg-muted px-1 py-0.5 text-[0.7rem]">gg-nginx</code>
      после правок конфига.
    </template>
    При отсутствии связи крутить и менять список на колесе доступно только с соответствующими правами.
  </p>
  <p
    v-else-if="socketConfigured && !socketConnected"
    class="text-center text-xs text-amber-800 dark:text-amber-200"
  >
    Подключение к синхронизации…
  </p>
  <p
    v-else-if="socketConfigured && socketConnected && !remoteSpin"
    class="text-center text-xs text-muted-foreground"
  >
    Загрузка состояния рулетки…
  </p>
</template>
