<script setup lang="ts">
const apiBase = (import.meta.env.VITE_API_URL ?? '/').replace(/\/+$/, '') || '';

function socialUrl(provider: string) {
  return `${apiBase}/auth/${provider}/redirect`;
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <a
      :href="socialUrl('yandex')"
      class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 text-sm font-medium shadow-sm transition-colors hover:bg-[#FC3F1D]/10 hover:text-[#FC3F1D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
        <path d="M13.62 21.75h2.56V2.25h-3.78c-4.03 0-6.15 2.08-6.15 5.12 0 2.56 1.28 4.03 3.62 5.56l-4.1 8.82h2.72l4.47-9.56-1.2-.83c-1.83-1.28-2.74-2.35-2.74-4.2 0-1.88 1.28-3.22 3.44-3.22h.92l.24 19.81Z"/>
      </svg>
      Яндекс
    </a>
<!--    <a-->
<!--      :href="socialUrl('vkontakte')"-->
<!--      class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 text-sm font-medium shadow-sm transition-colors hover:bg-[#0077FF]/10 hover:text-[#0077FF] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"-->
<!--    >-->
<!--      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">-->
<!--        <path d="M15.07 2H8.93C3.33 2 2 3.33 2 8.93v6.14C2 20.67 3.33 22 8.93 22h6.14C20.67 22 22 20.67 22 15.07V8.93C22 3.33 20.67 2 15.07 2Zm3.59 14.56h-1.79c-.67 0-.88-.54-2.09-1.76-1.05-1.02-1.52-1.15-1.78-1.15-.37 0-.47.1-.47.6v1.61c0 .43-.13.69-1.25.69-1.84 0-3.89-1.12-5.33-3.2C3.96 10.84 3.5 8.94 3.5 8.46c0-.26.1-.5.6-.5h1.79c.45 0 .62.2.79.68.87 2.52 2.33 4.74 2.93 4.74.23 0 .33-.1.33-.67V10.3c-.07-1.16-.68-1.26-.68-1.67 0-.2.17-.4.44-.4h2.81c.38 0 .51.2.51.65v3.06c0 .38.17.51.28.51.23 0 .42-.13.84-.55 1.3-1.46 2.23-3.71 2.23-3.71.12-.26.33-.5.78-.5h1.79c.54 0 .66.27.54.65-.23 1.05-2.44 4.18-2.44 4.18-.19.31-.26.45 0 .8.19.26.81.8 1.23 1.28.77.88 1.36 1.62 1.52 2.13.17.51-.08.77-.59.77Z"/>-->
<!--      </svg>-->
<!--      VK-->
<!--    </a>-->
  </div>
</template>
