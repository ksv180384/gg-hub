<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 36 });
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 100 100"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    class="site-logo shrink-0"
  >
    <g transform="translate(50, 50)">
      <text
        x="0"
        y="2"
        text-anchor="middle"
        font-family="system-ui, -apple-system, sans-serif"
        font-weight="900"
        font-size="58"
        class="logo-text"
      >
        GG
      </text>

      <text
        x="0"
        y="42"
        text-anchor="middle"
        font-family="system-ui, -apple-system, sans-serif"
        font-weight="700"
        font-size="28"
        textLength="68"
        lengthAdjust="spacingAndGlyphs"
        class="logo-text-sub"
      >
        HUB
      </text>
    </g>
  </svg>
</template>

<style scoped>
.logo-text {
  fill: var(--foreground);
}
.logo-text-sub {
  fill: var(--foreground);
  opacity: 0.45;
}
</style>
