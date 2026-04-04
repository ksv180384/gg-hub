import { Node, mergeAttributes } from '@tiptap/core';

export type VideoProvider = 'youtube' | 'vk';

export interface VideoEmbedOptions {
  width: number;
  height: number;
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    videoEmbed: {
      setVideoEmbed: (options: { src: string; width?: number; height?: number }) => ReturnType;
    };
  }
}

const YOUTUBE_EMBED_BASE = 'https://www.youtube.com/embed/';

/**
 * Добавляет enablejsapi=1 к YouTube embed URL для работы YouTube iframe API.
 */
function ensureYouTubeApiParams(src: string): string {
  if (!src.includes('youtube.com/embed/') && !src.includes('youtube-nocookie.com/embed/')) return src;
  try {
    const u = new URL(src);
    if (!u.searchParams.has('enablejsapi')) u.searchParams.set('enablejsapi', '1');
    return u.toString();
  } catch {
    return src;
  }
}

/**
 * Добавляет js_api=1 к VK embed URL для работы VK videoplayer.js.
 */
function ensureVkApiParams(src: string): string {
  if (!src.includes('vk.com/video_ext')) return src;
  try {
    const u = new URL(src);
    if (!u.searchParams.has('js_api')) u.searchParams.set('js_api', '1');
    return u.toString();
  } catch {
    return src;
  }
}

/**
 * Добавляет параметры API к URL видео (YouTube: enablejsapi=1, VK: js_api=1).
 */
export function ensureVideoApiParams(src: string): string {
  return ensureVkApiParams(ensureYouTubeApiParams(src));
}

/**
 * Парсит URL YouTube и возвращает embed URL с enablejsapi=1 или null.
 */
export function parseYouTubeUrl(url: string): string | null {
  const trimmed = url.trim();
  const patterns = [
    /(?:youtube\.com\/watch\?v=)([a-zA-Z0-9_-]{11})/,
    /(?:youtu\.be\/)([a-zA-Z0-9_-]{11})/,
    /(?:youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
  ];
  for (const re of patterns) {
    const m = trimmed.match(re);
    if (m) return ensureYouTubeApiParams(`${YOUTUBE_EMBED_BASE}${m[1]}`);
  }
  return null;
}

/**
 * Парсит URL VK и возвращает embed URL с js_api=1 или null.
 * Поддерживает: vk.com/video123_456, vk.com/clip123_456, vk.com/video?z=video123_456,
 * vk.com/video_ext.php?oid=...&id=...
 */
export function parseVkUrl(url: string): string | null {
  const trimmed = url.trim();
  // Прямая ссылка на embed (video_ext.php)
  if (trimmed.includes('vk.com/video_ext.php')) {
    try {
      const u = new URL(trimmed.startsWith('http') ? trimmed : `https://${trimmed}`);
      const oid = u.searchParams.get('oid');
      const id = u.searchParams.get('id');
      if (oid && id) return ensureVkApiParams(`${u.origin}${u.pathname}?${u.searchParams.toString()}`);
    } catch {
      return null;
    }
  }
  const m = trimmed.match(/vk\.com\/(?:video|clip)(-?\d+)_(\d+)/) ?? trimmed.match(/video\?z=video(-?\d+)_(\d+)/);
  if (m) {
    const oid = m[1];
    const id = m[2];
    if (oid && id) return ensureVkApiParams(`https://vk.com/video_ext.php?oid=${oid}&id=${id}`);
  }
  return null;
}

export function parseVideoUrl(url: string): { src: string; provider: VideoProvider } | null {
  const yt = parseYouTubeUrl(url);
  if (yt) return { src: yt, provider: 'youtube' };
  const vk = parseVkUrl(url);
  if (vk) return { src: vk, provider: 'vk' };
  return null;
}

export const VideoEmbed = Node.create<VideoEmbedOptions>({
  name: 'videoEmbed',

  group: 'block',

  atom: true,

  addOptions() {
    return {
      width: 640,
      height: 360,
    };
  },

  addAttributes() {
    return {
      src: {
        default: null,
        parseHTML: (el) => el.getAttribute('data-src'),
        renderHTML: (attrs) => (attrs.src ? { 'data-src': attrs.src } : {}),
      },
      width: {
        default: this.options.width,
        parseHTML: (el) => parseInt(el.getAttribute('data-width') ?? '', 10) || this.options.width,
        renderHTML: (attrs) => ({ 'data-width': String(attrs.width ?? this.options.width) }),
      },
      height: {
        default: this.options.height,
        parseHTML: (el) => parseInt(el.getAttribute('data-height') ?? '', 10) || this.options.height,
        renderHTML: (attrs) => ({ 'data-height': String(attrs.height ?? this.options.height) }),
      },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-video-embed]',
        getAttrs: (el) => {
          if (typeof el === 'string') return false;
          const div = el as HTMLElement;
          const raw = div.getAttribute('data-src') ?? div.querySelector('iframe')?.getAttribute('src');
          if (!raw) return false;
          const src = ensureVideoApiParams(raw);
          const width = parseInt(div.getAttribute('data-width') ?? '', 10) || this.options.width;
          const height = parseInt(div.getAttribute('data-height') ?? '', 10) || this.options.height;
          return { src, width, height };
        },
      },
      {
        tag: 'iframe[src*="youtube.com/embed"], iframe[src*="youtube-nocookie.com/embed"], iframe[src*="vk.com/video_ext"]',
        getAttrs: (el) => {
          if (typeof el === 'string') return false;
          const iframe = el as HTMLIFrameElement;
          const raw = iframe.getAttribute('src');
          if (!raw) return false;
          const src = ensureVideoApiParams(raw);
          const width = parseInt(iframe.getAttribute('width') ?? '', 10) || this.options.width;
          const height = parseInt(iframe.getAttribute('height') ?? '', 10) || this.options.height;
          return { src, width, height };
        },
      },
    ];
  },

  renderHTML({ node, HTMLAttributes }) {
    const { src, width, height } = node.attrs;
    if (!src) return ['div', { 'data-video-embed': '', class: 'video-embed-placeholder' }, 'Неверная ссылка'];

    return [
      'div',
      mergeAttributes(HTMLAttributes, {
        'data-video-embed': '',
        'data-src': src,
        'data-width': String(width ?? this.options.width),
        'data-height': String(height ?? this.options.height),
        class: 'video-embed-wrapper my-4 w-full max-w-full aspect-video relative overflow-hidden rounded-lg',
        style: `max-width: ${width ?? this.options.width}px;`,
      }),
      [
        'iframe',
        {
          src,
          width: '100%',
          height: '100%',
          frameborder: '0',
          allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
          allowfullscreen: 'true',
          title: 'Видео',
          class: 'absolute inset-0 w-full h-full rounded-lg',
        },
      ],
    ];
  },

  addCommands() {
    return {
      setVideoEmbed:
        (options) =>
        ({ commands }) => {
          const parsed = typeof options.src === 'string' ? parseVideoUrl(options.src) : null;
          if (!parsed) return false;
          const width = options.width ?? this.options.width;
          const height = options.height ?? this.options.height;
          return commands.insertContent({
            type: this.name,
            attrs: { src: parsed.src, width, height },
          });
        },
    };
  },
});
