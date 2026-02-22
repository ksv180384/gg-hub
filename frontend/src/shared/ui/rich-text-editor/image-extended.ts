import Image from '@tiptap/extension-image';
import { mergeAttributes } from '@tiptap/core';

type ImageAttrs = {
  width?: string | number | null;
  align?: string;
  wrap?: string;
};

export function imageStyle(attrs: ImageAttrs) {
  const w = attrs.width ? (typeof attrs.width === 'number' ? `${attrs.width}px` : String(attrs.width)) : 'auto';
  const wrap = attrs.wrap || 'none';

  if (wrap === 'left' || wrap === 'right') {
    const float = wrap;
    const margin = float === 'left' ? '0 1em 0.5em 0' : '0 0 0.5em 1em';
    return `max-width: 100%; width: ${w}; float: ${float}; margin: ${margin};`;
  }

  const align = attrs.align || 'center';
  const ml = align === 'right' ? 'auto' : align === 'left' ? '0' : 'auto';
  const mr = align === 'left' ? 'auto' : align === 'right' ? '0' : 'auto';
  return `max-width: 100%; width: ${w}; display: block; margin-left: ${ml}; margin-right: ${mr};`;
}

/**
 * Расширение Image с атрибутами width, align и wrap (размер, выравнивание, обтекание текстом).
 */
export const ImageWithSizeAlign = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      width: {
        default: null,
        parseHTML: (el) => el.getAttribute('data-width') ?? el.getAttribute('width'),
        renderHTML: (attrs) => (attrs.width ? { 'data-width': String(attrs.width) } : {}),
      },
      align: {
        default: 'center',
        parseHTML: (el) => (el.getAttribute('data-align') as 'left' | 'center' | 'right') || 'center',
        renderHTML: (attrs) => ({ 'data-align': attrs.align }),
      },
      wrap: {
        default: 'none',
        parseHTML: (el) => (el.getAttribute('data-wrap') as 'none' | 'left' | 'right') || 'none',
        renderHTML: (attrs) => ({ 'data-wrap': attrs.wrap }),
      },
    };
  },

  renderHTML({ node, HTMLAttributes }) {
    const attrs = node.attrs as ImageAttrs;
    return [
      'img',
      mergeAttributes(HTMLAttributes, {
        'data-wrap': attrs.wrap || 'none',
        style: imageStyle(attrs),
      }),
    ];
  },
});
