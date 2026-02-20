<script setup lang="ts">
import { computed } from 'vue';
import { TooltipRoot, TooltipTrigger, TooltipPortal, TooltipContent } from 'radix-vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  content?: string | null;
  side?: 'top' | 'right' | 'bottom' | 'left';
  class?: string;
}

const props = withDefaults(defineProps<Props>(), {
  content: '',
  side: 'top',
});

const hasContent = computed(() => (props.content ?? '').trim().length > 0);
</script>

<template>
  <TooltipRoot v-if="hasContent" :delay-duration="150">
    <TooltipTrigger as-child>
      <slot />
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        :side="side"
        :side-offset="6"
        :class="cn(
          'z-50 max-w-xs rounded-md border border-border bg-popover px-3 py-2 text-sm text-popover-foreground shadow-md',
          'animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
          'data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
          props.class
        )"
      >
        {{ content }}
      </TooltipContent>
    </TooltipPortal>
  </TooltipRoot>
  <slot v-else />
</template>
