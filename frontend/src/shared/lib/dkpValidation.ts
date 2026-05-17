export const DKP_COEFFICIENT_MAX = 999;
export const DKP_REASON_MAX_LENGTH = 5000;
export const DKP_COST_MAX = 1_000_000_000;

export function isValidDkpCoefficient(value: number): boolean {
  return Number.isFinite(value) && value >= 0 && value <= DKP_COEFFICIENT_MAX;
}

export function parseDkpCostInput(raw: string): number | null {
  const trimmed = raw.trim();
  if (!trimmed) {
    return null;
  }
  const num = Number(trimmed);
  if (!Number.isFinite(num) || num < 0) {
    return null;
  }
  const truncated = Math.trunc(num);
  if (truncated !== num) {
    return null;
  }
  if (truncated > DKP_COST_MAX) {
    return null;
  }
  return truncated;
}

export function isValidDkpAdjustReason(reason: string): boolean {
  return reason.length <= DKP_REASON_MAX_LENGTH;
}
