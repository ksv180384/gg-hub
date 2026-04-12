/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_SITE_URL?: string;
  readonly VITE_OG_IMAGE_URL?: string;
  /**
   * Socket.IO для рулетки: полный URL, доступный из браузера; пусто — тот же origin + путь /socket.io (прокси nginx/Vite).
   * `off` / `false` — не подключаться.
   */
  readonly VITE_SOCKET_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
