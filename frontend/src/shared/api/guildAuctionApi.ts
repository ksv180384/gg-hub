import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';
import type { GuildBankItem, GuildBankItemTier } from '@/shared/api/guildBankApi';

export type GuildAuctionBid = {
  id: number;
  lot_id: number;
  user_id: number;
  user_name: string | null;
  character_id: number | null;
  character_name: string | null;
  character_avatar_url: string | null;
  amount: number;
  created_at: string;
};

export type GuildAuctionLot = {
  id: number;
  status: 'active' | 'closed' | 'cancelled';
  guild_bank_item_id: number;
  quantity: number;
  item: {
    id: number;
    name: string;
    description: string | null;
    quantity: number | null;
    dkp_cost: number | null;
    tier: Pick<GuildBankItemTier, 'id' | 'name' | 'color'> | null;
  } | null;
  start_price: number;
  created_by_user_id: number | null;
  created_by_user_name: string | null;
  created_by_character_id: number | null;
  created_by_character_name: string | null;
  closed_by_user_id: number | null;
  closed_by_user_name: string | null;
  closed_by_character_id: number | null;
  closed_by_character_name: string | null;
  current_bid_amount: number | null;
  current_bid_user_id: number | null;
  current_bid_user_name: string | null;
  current_bid_character_id: number | null;
  current_bid_character_name: string | null;
  current_bid_character_avatar_url: string | null;
  winner_user_id: number | null;
  winner_user_name: string | null;
  guild_bank_item_grant_id: number | null;
  grant?: {
    id: number;
    received_by_character_id: number | null;
    received_by_character_name: string | null;
    quantity: number;
    dkp_charged: number | null;
    reason: string | null;
    granted_at: string | null;
  } | null;
  ends_at: string;
  closed_at: string | null;
  created_at: string;
  bids: GuildAuctionBid[];
};

export type GuildAuctionContext = {
  my_permission_slugs: string[];
  auction_bids_count: number;
  closed_lots_count: number;
  dkp_enabled: boolean;
  my_dkp_balance: number;
  my_characters: Array<{ id: number; name: string }>;
};

export type CreateGuildAuctionPayload = {
  ends_at: string;
  lots: Array<{
    guild_bank_item_id: number;
    quantity: number;
    start_price?: number | null;
  }>;
};

export type GuildAuctionHistoryParams = {
  page?: number;
  dateFrom?: string;
  dateTo?: string;
  sort?: 'asc' | 'desc';
};

export type GuildAuctionHistoryPage = {
  lots: GuildAuctionLot[];
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export const guildAuctionApi = {
  unwrap<T>(res: { data: unknown }): T {
    const raw = res.data as { data?: T } | T | null;
    if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data!;
    return raw as T;
  },

  async getContext(guildId: number): Promise<GuildAuctionContext> {
    const res = await http.fetchGet<{ data: GuildAuctionContext }>(`/guilds/${guildId}/auction/context`);
    throwOnError(res, 'Не удалось загрузить контекст аукциона.');
    return res.data?.data ?? {
      my_permission_slugs: [],
      dkp_enabled: false,
      my_dkp_balance: 0,
      my_characters: [],
      auction_bids_count: 0,
      closed_lots_count: 0,
    };
  },

  async listLots(guildId: number): Promise<GuildAuctionLot[]> {
    const res = await http.fetchGet<{ data: GuildAuctionLot[] }>(`/guilds/${guildId}/auction/lots`);
    throwOnError(res, 'Не удалось загрузить лоты аукциона.');
    return res.data?.data ?? [];
  },

  async listClosedLots(
    guildId: number,
    params: GuildAuctionHistoryParams = {},
  ): Promise<GuildAuctionHistoryPage> {
    const query = new URLSearchParams();
    if (params.page) query.set('page', String(params.page));
    if (params.dateFrom) query.set('date_from', params.dateFrom);
    if (params.dateTo) query.set('date_to', params.dateTo);
    if (params.sort) query.set('sort', params.sort);

    const suffix = query.size > 0 ? `?${query.toString()}` : '';
    const res = await http.fetchGet<{
      data: GuildAuctionLot[];
      meta: PaginationMeta;
    }>(`/guilds/${guildId}/auction/history${suffix}`);
    throwOnError(res, 'Не удалось загрузить историю аукциона.');

    const meta = res.data?.meta;
    return {
      lots: res.data?.data ?? [],
      currentPage: meta?.current_page ?? 1,
      lastPage: meta?.last_page ?? 1,
      perPage: meta?.per_page ?? 50,
      total: meta?.total ?? 0,
    };
  },

  async getLot(guildId: number, lotId: number): Promise<GuildAuctionLot> {
    const res = await http.fetchGet<{ data: GuildAuctionLot }>(`/guilds/${guildId}/auction/lots/${lotId}`);
    throwOnError(res, 'Не удалось загрузить лот аукциона.');
    return res.data?.data as GuildAuctionLot;
  },

  async createLots(guildId: number, payload: CreateGuildAuctionPayload): Promise<GuildAuctionLot[]> {
    const res = await http.fetchPost<{ data: GuildAuctionLot[] }>(
      `/guilds/${guildId}/auction/lots`,
      payload as unknown as Record<string, unknown>,
    );
    throwOnError(res, 'Не удалось выставить предметы на аукцион.');
    return res.data?.data ?? [];
  },

  async bid(guildId: number, lotId: number, amount: number, characterId: number | null): Promise<GuildAuctionLot> {
    const res = await http.fetchPost<GuildAuctionLot>(
      `/guilds/${guildId}/auction/lots/${lotId}/bid`,
      { amount, character_id: characterId },
    );
    throwOnError(res, 'Не удалось сделать ставку.');
    return this.unwrap<GuildAuctionLot>(res as { data: unknown });
  },

  async close(guildId: number, lotId: number): Promise<GuildAuctionLot> {
    const res = await http.fetchPost<GuildAuctionLot>(`/guilds/${guildId}/auction/lots/${lotId}/close`, {});
    throwOnError(res, 'Не удалось закрыть лот.');
    return this.unwrap<GuildAuctionLot>(res as { data: unknown });
  },
};

export type GuildAuctionBankItem = GuildBankItem;
