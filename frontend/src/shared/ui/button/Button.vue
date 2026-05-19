<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import type { ClassValue } from 'clsx';
import { cn } from '@/shared/lib/utils';

const buttonVariants = cva(
  'inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-semibold tracking-normal transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:ring-offset-0 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        default:
          'border border-primary bg-primary text-primary-foreground shadow-none hover:bg-primary/90 dark:border-transparent',
        destructive:
          'border border-destructive bg-destructive text-destructive-foreground shadow-none hover:bg-destructive/90',
        success:
          'border border-green-600 bg-green-600 text-white shadow-none hover:bg-green-700',
        outline:
          'border border-border bg-card text-foreground shadow-none hover:border-primary/25 hover:bg-accent/60 hover:text-foreground',
        outlinePrimary:
          'border border-primary/20 bg-primary/5 text-primary shadow-none hover:border-primary/35 hover:bg-primary/10 hover:text-primary',
        secondary:
          'border border-border bg-secondary text-secondary-foreground shadow-none hover:bg-secondary/80',
        ghost:
          'text-muted-foreground hover:bg-accent/70 hover:text-foreground',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-9 px-4 py-2',
        sm: 'h-8 px-3 text-xs',
        lg: 'h-10 px-5',
        icon: 'h-8 w-8',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
);

type ButtonVariants = VariantProps<typeof buttonVariants>;

interface Props {
  variant?: ButtonVariants['variant'];
  size?: ButtonVariants['size'];
  type?: 'button' | 'submit' | 'reset';
  class?: ClassValue;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  size: 'default',
  type: 'button',
});
</script>

<template>
  <button
    :type="props.type"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </button>
</template>
