export type DkpParticipantInput = {
  character_id?: number | null;
  dkp_coefficient?: number | string | null;
  dkp_points_override?: number | null;
};

function normalizeCoefficient(coefficient: number | string | null | undefined): number {
  if (coefficient != null && coefficient !== '' && Number.isFinite(Number(coefficient))) {
    return Number(coefficient);
  }
  return 1;
}

function sumGuildCoefficients(participants: DkpParticipantInput[]): number {
  return participants
    .filter((p) => p.character_id != null && p.dkp_points_override == null)
    .reduce((sum, p) => sum + normalizeCoefficient(p.dkp_coefficient), 0);
}

/**
 * Соответствует Domains\GuildDkp\Support\CalculateEventParticipantDkpPoints::resolveAll.
 */
export function calculateEventParticipantDkpPoints(
  basePoints: number | null | undefined,
  participant: DkpParticipantInput,
  options?: {
    distributeTotal?: boolean;
    guildParticipants?: DkpParticipantInput[];
  }
): number | null {
  if (participant.dkp_points_override != null) {
    return participant.dkp_points_override;
  }

  if (basePoints == null || participant.character_id == null) {
    return null;
  }

  const coefficient = normalizeCoefficient(participant.dkp_coefficient);

  if (options?.distributeTotal) {
    const pool = options.guildParticipants ?? [];
    const sumCoefficients = sumGuildCoefficients(pool);
    if (sumCoefficients <= 0) {
      return null;
    }
    return Math.round((basePoints * coefficient) / sumCoefficients);
  }

  return Math.round(basePoints * coefficient);
}

export function parseDkpBasePointsInput(value: string): number | null {
  const raw = value.trim();
  if (!raw) return null;
  const num = Number(raw);
  if (!Number.isFinite(num) || num < 0) return null;
  return Math.trunc(num);
}
