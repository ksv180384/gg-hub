/**
 * Payload события `auction:spin` (генерируется в `socket_server/auctionSocketHandler.js`).
 * Угол остановки на клиенте считается по `norm`, `fullTurns`, `duration`; см. также
 * `frontend/src/widgets/spin-wheel/docs/SPIN_WHEEL_ROTATION.md`.
 */
export type SpinWheelServerParams = {
  /** Индекс победителя (для логов; геометрия — через `norm`). */
  winIdx: number;
  /** Угол «лотереи» на окружности сегментов, градусы. */
  norm: number;
  /** Полные обороты 360°, 0…120 (кламп на клиенте; N≈8×T/4с). */
  fullTurns: number;
  /** Длительность анимации на клиенте, мс. */
  duration: number;
};
