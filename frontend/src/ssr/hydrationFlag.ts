/**
 * Флаг SSR-гидратации: true, если клиент восстанавливает состояние из __INITIAL_PINIA__
 * и первый роутерный beforeEach ещё не завершился.
 * Используется для пропуска повторного fetch (context/user) при начальной гидратации.
 */
let hydrating = false;

export function setHydrating(value: boolean): void {
  hydrating = value;
}

export function isHydrating(): boolean {
  return hydrating;
}
