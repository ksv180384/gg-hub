/** Сколько тегов показывать в карточке / блоке «Теги» (остальные — счётчик «+N»). */
export const ROSTER_TAGS_DISPLAY_MAX = 10;

/** Тег в ответе состава гильдии. */
export type RosterTagItem = {
  id: number;
  name: string;
  slug: string;
  used_by_user_id?: number | null;
  used_by_guild_id?: number | null;
};

export type RosterTagRow = {
  tag: RosterTagItem;
  source: 'guild' | 'personal';
};

/** Общий тег: не привязан ни к пользователю, ни к гильдии (поля used_by_* пустые). */
export function isRosterCommonTag(tag: RosterTagItem): boolean {
  return tag.used_by_user_id == null && tag.used_by_guild_id == null;
}

/**
 * Порядок вывода назначенных тегов: общие → гильдия → пользователь.
 */
export function rosterTagCategoryOrder(tag: RosterTagItem): 0 | 1 | 2 {
  if (isRosterCommonTag(tag)) {
    return 0;
  }
  if (tag.used_by_guild_id != null) {
    return 1;
  }
  if (tag.used_by_user_id != null) {
    return 2;
  }
  return 0;
}

export function sortRosterTagRows(rows: RosterTagRow[]): RosterTagRow[] {
  return [...rows].sort((a, b) => {
    const ca = rosterTagCategoryOrder(a.tag);
    const cb = rosterTagCategoryOrder(b.tag);
    if (ca !== cb) {
      return ca - cb;
    }
    return a.tag.name.localeCompare(b.tag.name, 'ru');
  });
}

export function sliceRosterTagRowsForDisplay(rows: RosterTagRow[]): {
  visible: RosterTagRow[];
  moreCount: number;
} {
  const visible = rows.slice(0, ROSTER_TAGS_DISPLAY_MAX);
  return { visible, moreCount: rows.length - visible.length };
}

/**
 * Классы бейджа: гильдия — фиолетовый; общий тег — синий; иначе (личный) — только outline по умолчанию.
 */
export function rosterTagBadgeClass(source: 'guild' | 'personal', tag: RosterTagItem): string {
  if (source === 'guild') {
    return 'border-violet-500/50 bg-violet-500/15 text-violet-900 dark:text-violet-200';
  }
  if (isRosterCommonTag(tag)) {
    return 'border-blue-500/50 bg-blue-500/15 text-blue-900 dark:text-blue-200';
  }
  return '';
}

export function rosterTagDisplayRows(member: {
  tags: RosterTagItem[];
  personal_tags?: RosterTagItem[];
}): RosterTagRow[] {
  const guildIds = new Set(member.tags.map((t) => t.id));
  const rows: RosterTagRow[] = [];
  for (const t of member.tags) {
    rows.push({ tag: t, source: 'guild' });
  }
  for (const t of member.personal_tags ?? []) {
    if (!guildIds.has(t.id)) {
      rows.push({ tag: t, source: 'personal' });
    }
  }
  return sortRosterTagRows(rows);
}
