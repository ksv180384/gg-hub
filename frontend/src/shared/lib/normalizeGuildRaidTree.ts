import type { RaidItem } from '@/shared/api/guildsApi';

/**
 * Собирает уникальные рейды из ответа API (на случай дублей в дереве).
 */
function flattenRaidTreeUnique(nodes: RaidItem[]): RaidItem[] {
  const seen = new Map<number, RaidItem>();
  function walk(list: RaidItem[]) {
    for (const n of list) {
      if (!seen.has(n.id)) {
        seen.set(n.id, n);
      }
      if (n.children?.length) {
        walk(n.children);
      }
    }
  }
  walk(nodes);
  return [...seen.values()];
}

/**
 * Пересобирает дерево рейдов строго по parent_id и sort_order.
 * Исправляет случаи, когда вложенность в `children` не совпадает с parent_id
 * (из-за гонок при DnD, кэша или бага бэкенда) — иначе карточка «теряет» отступ.
 */
export function normalizeGuildRaidTree(roots: RaidItem[]): RaidItem[] {
  const flat = flattenRaidTreeUnique(roots);
  if (flat.length === 0) {
    return [];
  }

  const allIds = new Set(flat.map((r) => r.id));
  const byParent = new Map<number | null, RaidItem[]>();

  for (const r of flat) {
    let pid: number | null = r.parent_id ?? null;
    if (pid !== null && !allIds.has(pid)) {
      pid = null;
    }
    const arr = byParent.get(pid) ?? [];
    arr.push(r);
    byParent.set(pid, arr);
  }

  for (const [, arr] of byParent) {
    arr.sort((a, b) => {
      const so = (a.sort_order ?? 0) - (b.sort_order ?? 0);
      if (so !== 0) {
        return so;
      }
      return a.id - b.id;
    });
  }

  function build(pid: number | null): RaidItem[] {
    const layer = byParent.get(pid) ?? [];
    return layer.map((node) => {
      const childNodes = build(node.id);
      return {
        ...node,
        children: childNodes.length > 0 ? childNodes : undefined,
      };
    });
  }

  return build(null);
}
