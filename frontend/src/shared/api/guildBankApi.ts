/**
 * API банка/хранилища гильдии.
 */

import { throwOnError } from '@/shared/api/errors';
import { http } from '@/shared/api/http';

export type GuildBankItemTier = {
  id: number;
  name: string;
  color: string | null;
  items_count?: number;
  guild_id?: number;
  created_at?: string;
  updated_at?: string;
};

export type GuildBankItem = {
  id: number;
  name: string;
  description: string | null;
  guild_bank_item_tier_id: number | null;
  tier: GuildBankItemTier | null;
  dkp_cost: number | null;
  quantity: number | null;
  grants_count?: number;
  guild_id?: number;
  last_granted_at?: string | null;
  created_at?: string;
  updated_at?: string;
};

export type GuildBankGrant = {
  id: number;
  received_by_character_id: number;
  granted_at: string;
  reason: string;
  dkp_charged?: number | null;
  guild_bank_item_id?: number;
  guild_id?: number;
  granted_by_character_id?: number | null;
  item?: {
    id: number;
    name: string;
    tier: Pick<GuildBankItemTier, 'id' | 'name' | 'color'> | null;
    dkp_cost: number | null;
    guild_bank_item_tier_id?: number | null;
    quantity?: number | null;
  };
  received_by_character?: { id: number; name: string };
  granted_by_character?: { id: number; name: string } | null;
  created_at?: string;
  updated_at?: string;
};

export type GuildBankPageContext = {
  my_permission_slugs: string[];
  dkp_enabled: boolean;
  dkp_ledger_available: boolean;
  my_dkp_balance: number | null;
};

export type CreateGuildBankItemPayload = {
  name: string;
  description?: string | null;
  guild_bank_item_tier_id?: number | null;
  dkp_cost?: number | null;
  quantity?: number | null;
};

export type UpdateGuildBankItemPayload = Partial<CreateGuildBankItemPayload>;

export type CreateGuildBankItemTierPayload = {
  name: string;
  color: string;
};

export type CreateGuildBankGrantPayload = {
  guild_bank_item_id: number;
  received_by_character_id: number;
  granted_by_character_id?: number | null;
  reason?: string | null;
  granted_at?: string | null;
  confirm_negative_balance?: boolean;
};

export type GuildBankGrantDkpConfirmation = {
  requires_confirmation: boolean;
  balance: number;
  charged: number;
  balance_after: number;
};

export const guildBankApi = {
  unwrap<T>(res: { data: unknown }): T {
    const raw = res.data as { data?: T } | T | null;
    if (raw && typeof raw === 'object' && 'data' in raw) return (raw as { data: T }).data!;
    return raw as T;
  },

  async getPageContext(guildId: number): Promise<GuildBankPageContext> {
    const res = await http.fetchGet<{ data: GuildBankPageContext }>(`/guilds/${guildId}/bank/context`);
    throwOnError(res, 'Не удалось загрузить контекст банка.');
    return res.data?.data ?? { my_permission_slugs: [], dkp_enabled: false, dkp_ledger_available: false, my_dkp_balance: null };
  },

  async listTiers(guildId: number): Promise<GuildBankItemTier[]> {
    const res = await http.fetchGet<{ data: GuildBankItemTier[] }>(`/guilds/${guildId}/bank/tiers`);
    throwOnError(res, 'Не удалось загрузить тиры банка.');
    return res.data?.data ?? [];
  },

  async createTier(guildId: number, payload: CreateGuildBankItemTierPayload): Promise<GuildBankItemTier> {
    const res = await http.fetchPost<GuildBankItemTier>(`/guilds/${guildId}/bank/tiers`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось добавить тир.');
    return this.unwrap<GuildBankItemTier>(res as { data: unknown });
  },

  async deleteTier(guildId: number, tierId: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/guilds/${guildId}/bank/tiers/${tierId}`);
    if (res.status >= 400) {
      throwOnError(res, 'Не удалось удалить тир.');
    }
  },

  async listItems(guildId: number): Promise<GuildBankItem[]> {
    const res = await http.fetchGet<{ data: GuildBankItem[] }>(`/guilds/${guildId}/bank/items`);
    throwOnError(res, 'Не удалось загрузить предметы банка.');
    return res.data?.data ?? [];
  },

  async createItem(guildId: number, payload: CreateGuildBankItemPayload): Promise<GuildBankItem> {
    const res = await http.fetchPost<GuildBankItem>(`/guilds/${guildId}/bank/items`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось добавить предмет.');
    return this.unwrap<GuildBankItem>(res as { data: unknown });
  },

  async updateItem(guildId: number, itemId: number, payload: UpdateGuildBankItemPayload): Promise<GuildBankItem> {
    const res = await http.fetchPut<GuildBankItem>(`/guilds/${guildId}/bank/items/${itemId}`, payload as Record<string, unknown>);
    throwOnError(res, 'Не удалось сохранить предмет.');
    return this.unwrap<GuildBankItem>(res as { data: unknown });
  },

  async deleteItem(guildId: number, itemId: number): Promise<void> {
    const res = await http.fetchDelete<unknown>(`/guilds/${guildId}/bank/items/${itemId}`);
    if (res.status >= 400) {
      throwOnError(res, 'Не удалось удалить предмет.');
    }
  },

  async listItemGrants(guildId: number, itemId: number): Promise<GuildBankGrant[]> {
    const res = await http.fetchGet<{ data: GuildBankGrant[] }>(`/guilds/${guildId}/bank/items/${itemId}/grants`);
    throwOnError(res, 'Не удалось загрузить историю выдач.');
    return res.data?.data ?? [];
  },

  async createGrant(
    guildId: number,
    payload: CreateGuildBankGrantPayload,
  ): Promise<GuildBankGrant> {
    const res = await http.fetchPost<GuildBankGrant>(
      `/guilds/${guildId}/bank/grants`,
      payload as Record<string, unknown>,
    );
    if (res.status >= 400) {
      const body = res.data as {
        message?: string;
        errors?: Record<string, string[]>;
        data?: GuildBankGrantDkpConfirmation;
      } | null;
      const err = new Error(
        body?.errors?.confirm_negative_balance?.[0]
          ?? body?.message
          ?? 'Не удалось выдать предмет.',
      ) as Error & {
        status?: number;
        errors?: Record<string, string[]>;
        dkpConfirmation?: GuildBankGrantDkpConfirmation;
      };
      err.status = res.status;
      if (body?.errors) err.errors = body.errors;
      if (body?.data?.requires_confirmation) err.dkpConfirmation = body.data;
      throw err;
    }
    return this.unwrap<GuildBankGrant>(res as { data: unknown });
  },

  async revokeGrant(guildId: number, grantId: number): Promise<{ id: number }> {
    const res = await http.fetchDelete<unknown>(`/guilds/${guildId}/bank/grants/${grantId}`);
    throwOnError(res, 'Не удалось отменить выдачу.');
    return this.unwrap<{ id: number }>(res as { data: unknown });
  },

  async listMemberGrants(guildId: number, characterId: number): Promise<GuildBankGrant[]> {
    const res = await http.fetchGet<{ data: GuildBankGrant[] }>(`/guilds/${guildId}/bank/members/${characterId}/grants`);
    throwOnError(res, 'Не удалось загрузить предметы участника.');
    return res.data?.data ?? [];
  },
};
