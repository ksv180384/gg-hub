import type { Tag } from '@/shared/api/tagsApi';

/**
 * Порядок в выпадающем списке тегов: общие → гильдия → пользователь (как на карточке состава).
 */
export function tagPickerCategoryOrder(tag: Pick<Tag, 'used_by_user_id' | 'used_by_guild_id'>): 0 | 1 | 2 {
  if (tag.used_by_user_id == null && tag.used_by_guild_id == null) {
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

export function sortTagsForPicker<T extends Pick<Tag, 'name' | 'used_by_user_id' | 'used_by_guild_id'>>(tags: T[]): T[] {
  return [...tags].sort((a, b) => {
    const ca = tagPickerCategoryOrder(a);
    const cb = tagPickerCategoryOrder(b);
    if (ca !== cb) {
      return ca - cb;
    }
    return a.name.localeCompare(b.name, 'ru');
  });
}
