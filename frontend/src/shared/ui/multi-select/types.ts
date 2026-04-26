export interface MultiSelectOption {
  value: string | number;
  label: string;
  disabled?: boolean;
  /**
   * Если задано — опция (и выбранные значения при displayMode='badges') отображаются бейджем с этим классом.
   * Полностью опционально для обратной совместимости.
   */
  badgeClass?: string;
  /** Миниатюра слева от подписи (список и чипы на триггере). */
  imageUrl?: string | null;
}
