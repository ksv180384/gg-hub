/**
 * Соответствует Domains\GuildDkp\Support\CalculateEventParticipantDkpPoints.
 */
export function calculateEventParticipantDkpPoints(
  basePoints: number | null | undefined,
  coefficient: number | string | null | undefined,
  override: number | null | undefined
): number | null {
  if (override != null) {
    return override;
  }

  if (basePoints == null) {
    return null;
  }

  const coef =
    coefficient != null && coefficient !== '' && Number.isFinite(Number(coefficient))
      ? Number(coefficient)
      : 1;

  return Math.round(basePoints * coef);
}

export function parseDkpBasePointsInput(value: string): number | null {
  const raw = value.trim();
  if (!raw) return null;
  const num = Number(raw);
  if (!Number.isFinite(num) || num < 0) return null;
  return Math.trunc(num);
}
