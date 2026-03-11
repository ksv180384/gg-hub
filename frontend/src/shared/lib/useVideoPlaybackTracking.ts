import { onMounted, onUnmounted, type Ref } from 'vue';
import { postsApi } from '@/shared/api/postsApi';

/**
 * Отслеживает воспроизведение видео (YouTube, VK) через postMessage и засчитывает просмотр поста.
 * Не зависит от загрузки сторонних скриптов — работает для всех iframe.
 */
export function useVideoPlaybackTracking(
  containerRef: Ref<HTMLElement | null | undefined>,
  options: {
    guildId: Ref<number> | number;
    postId: Ref<number> | number;
    /** Вызывается при успешной записи просмотра (для обновления UI). */
    onRecorded?: () => void;
  }
) {
  const guildId = () => (typeof options.guildId === 'object' ? options.guildId.value : options.guildId);
  const postId = () => (typeof options.postId === 'object' ? options.postId.value : options.postId);
  let recorded = false;
  const cleanupFns: Array<() => void> = [];
  const initializedIframes = new WeakSet<HTMLIFrameElement>();

  function recordView() {
    if (recorded) return;
    const gid = guildId();
    const pid = postId();
    if (!gid || !pid) return;
    recorded = true;
    postsApi.recordGuildPostView(gid, pid).then((wasRecorded) => {
      if (wasRecorded) options.onRecorded?.();
    }).catch(() => {
      recorded = false;
    });
  }

  function setupYouTubePlayers(container: HTMLElement) {
    const iframes = container.querySelectorAll<HTMLIFrameElement>(
      'iframe[src*="youtube.com/embed"], iframe[src*="youtube-nocookie.com/embed"]'
    );

    iframes.forEach((iframe) => {
      if (initializedIframes.has(iframe)) return;
      const src = iframe.src || iframe.getAttribute('src') || '';
      if (!src.includes('enablejsapi')) return;

      initializedIframes.add(iframe);

      const handler = (e: MessageEvent) => {
        if (e.source !== iframe.contentWindow) return;
        let data: unknown;
        try {
          data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
        } catch {
          return;
        }
        if (data && typeof data === 'object' && 'event' in data && (data as { event: string }).event === 'infoDelivery') {
          const info = (data as { info?: { playerState?: number } }).info;
          if (info?.playerState === 1) recordView();
        }
      };

      const sendListening = () => {
        try {
          iframe.contentWindow?.postMessage(
            '{"event":"listening","id":1,"channel":"widget"}',
            '*'
          );
        } catch {
          // ignore
        }
      };

      window.addEventListener('message', handler);
      cleanupFns.push(() => window.removeEventListener('message', handler));

      iframe.addEventListener('load', sendListening);
      cleanupFns.push(() => iframe.removeEventListener('load', sendListening));
      sendListening();
      // Повторные отправки — iframe может загружаться с задержкой
      const t1 = setTimeout(sendListening, 300);
      const t2 = setTimeout(sendListening, 1000);
      cleanupFns.push(() => { clearTimeout(t1); clearTimeout(t2); });
    });
  }

  function setupVkPlayers(container: HTMLElement) {
    const iframes = container.querySelectorAll<HTMLIFrameElement>(
      'iframe[src*="vk.com/video_ext"]'
    );

    iframes.forEach((iframe) => {
      if (initializedIframes.has(iframe)) return;
      const src = iframe.src || iframe.getAttribute('src') || '';
      if (!src.includes('js_api')) return;

      initializedIframes.add(iframe);
      const setupTime = Date.now();
      const MIN_DELAY_MS = 1500; // Игнорируем события в первые 1.5 с — VK шлёт inited/timeupdate при загрузке

      const handler = (e: MessageEvent) => {
        if (e.source !== iframe.contentWindow) return;
        if (Date.now() - setupTime < MIN_DELAY_MS) return;
        let data = e.data;
        if (typeof data === 'string') {
          try {
            data = JSON.parse(data);
          } catch {
            if (data === 'embed-play') recordView();
            return;
          }
        }
        const ev = data && typeof data === 'object' && 'event' in data
          ? (data as { event: string }).event
          : data;
        // Только события реального воспроизведения; timeupdate/inited при загрузке — не считаем
        if (ev === 'started' || ev === 'resumed' || ev === 'embed-play') {
          recordView();
        }
      };

      window.addEventListener('message', handler);
      cleanupFns.push(() => window.removeEventListener('message', handler));
    });
  }

  let observer: MutationObserver | null = null;

  onMounted(() => {
    const container = containerRef.value;
    if (!container) return;

    const run = () => {
      if (!containerRef.value) return;
      setupYouTubePlayers(containerRef.value);
      setupVkPlayers(containerRef.value);
    };

    run();
    observer = new MutationObserver(run);
    observer.observe(container, { childList: true, subtree: true });
    setTimeout(run, 100);
    setTimeout(run, 500);
  });

  onUnmounted(() => {
    observer?.disconnect();
    observer = null;
    cleanupFns.forEach((fn) => fn());
    cleanupFns.length = 0;
  });
}
