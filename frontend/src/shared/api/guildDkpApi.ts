import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type GuildDkpLedgerSource = 'event' | 'manual' | 'bank_grant' | 'bank_grant_revoke';

export type GuildDkpLedgerEntry = {
  id: number;
  occurred_at: string;
  amount: number;
  source: GuildDkpLedgerSource;
  reason: string | null;
  balance_after: number;
  user?: { id: number; name: string };
  actor_user?: { id: number; name: string } | null;
  character?: { id: number; name: string } | null;
  guild_bank_item?: { id: number; name: string } | null;
  event_history?: { id: number; title: string; occurred_at: string | null } | null;
};

export type GuildUserDkpBalance = {
  user_id: number;
  balance: number;
};

export type AdjustGuildUserDkpPayload = {
  amount: number;
  reason?: string | null;
};

export type GrantDkpConfirmation = {
  requires_confirmation: boolean;
  balance: number;
  charged: number;
  balance_after: number;
};

export type GuildDkpLedgerListParams = {
  occurred_from?: string;
  occurred_to?: string;
  user_name?: string;
  event_history_title_id?: number;
  source?: GuildDkpLedgerSource;
  page?: number;
  per_page?: number;
};

export type GuildDkpLedgerListResult = {
  data: GuildDkpLedgerEntry[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export const guildDkpApi = {
  async listLedger(
    guildId: number,
    params: GuildDkpLedgerListParams = {},
  ): Promise<GuildDkpLedgerListResult> {
    const query: Record<string, string | number> = {};
    const occurredFrom = params.occurred_from?.trim();
    const occurredTo = params.occurred_to?.trim();
    const userName = params.user_name?.trim();
    if (occurredFrom) query.occurred_from = occurredFrom;
    if (occurredTo) query.occurred_to = occurredTo;
    if (userName) query.user_name = userName;
    if (params.event_history_title_id != null) {
      query.event_history_title_id = params.event_history_title_id;
    }
    if (params.source) query.source = params.source;
    if (params.page != null) query.page = params.page;
    if (params.per_page != null) query.per_page = params.per_page;

    const res = await http.fetchGet<{
      data: GuildDkpLedgerEntry[];
      meta?: GuildDkpLedgerListResult['meta'];
    }>(`/guilds/${guildId}/dkp/ledger`, {
      params: Object.keys(query).length ? query : undefined,
    });
    throwOnError(res, 'Не удалось загрузить историю ДКП.');
    const payload = res.data;
    return {
      data: payload?.data ?? [],
      meta: payload?.meta ?? {
        current_page: 1,
        last_page: 1,
        per_page: payload?.data?.length ?? 0,
        total: payload?.data?.length ?? 0,
      },
    };
  },

  async getMemberBalance(guildId: number, characterId: number): Promise<GuildUserDkpBalance> {
    const res = await http.fetchGet<{ data: GuildUserDkpBalance }>(
      `/guilds/${guildId}/members/${characterId}/dkp`,
    );
    throwOnError(res, 'Не удалось загрузить баланс ДКП.');
    return res.data?.data ?? { user_id: 0, balance: 0 };
  },

  async adjustMemberBalance(
    guildId: number,
    characterId: number,
    payload: AdjustGuildUserDkpPayload,
  ): Promise<GuildDkpLedgerEntry> {
    const res = await http.fetchPost<GuildDkpLedgerEntry>(
      `/guilds/${guildId}/members/${characterId}/dkp/adjust`,
      payload as Record<string, unknown>,
    );
    throwOnError(res, 'Не удалось изменить баланс ДКП.');
    const raw = res.data as { data?: GuildDkpLedgerEntry } | GuildDkpLedgerEntry | null;
    if (raw && typeof raw === 'object' && 'data' in raw) return raw.data!;
    return raw as GuildDkpLedgerEntry;
  },
};
